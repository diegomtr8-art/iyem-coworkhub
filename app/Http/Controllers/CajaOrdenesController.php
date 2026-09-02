<?php

namespace App\Http\Controllers;

use App\Enums\EstadoFacturaOrden;
use App\Enums\EstadoPagoOrden;
use App\Enums\MetodoReferencia;
use App\Models\Comunicado;
use App\Models\OrdenPago;
use App\Notifications\FacturaEmitida;
use App\Notifications\PagoConfirmado;
use App\Servicios\Pagos\ConfirmadorDeOrden;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Caja y contabilidad (Fase 4). Detrás del permiso `gestionar-facturacion`.
 *
 * La bandeja de órdenes con referencia, la confirmación del pago —que activa la
 * membresía—, y la cancelación. Nunca se activa una membresía de forma
 * automática: siempre una persona con permiso, con evidencia y con registro.
 */
class CajaOrdenesController extends Controller
{
    public function __construct(private readonly ConfirmadorDeOrden $confirmador)
    {
    }

    /** Fase 4.1 — bandeja de órdenes con filtros y buscador tolerante. */
    public function index(Request $request): Response
    {
        $filtros = $request->only(['estado', 'metodo', 'desde', 'hasta', 'buscar']);

        $ordenes = OrdenPago::query()
            ->with(['user:id,name', 'plan:id,nombre'])
            ->when($filtros['estado'] ?? null, fn ($q, $e) => $q->where('estado_pago', $e))
            ->when($filtros['metodo'] ?? null, fn ($q, $m) => $q->where('metodo', $m))
            ->when($filtros['desde'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filtros['hasta'] ?? null, fn ($q, $h) => $q->whereDate('created_at', '<=', $h))
            // Buscador tolerante: por referencia (normalizada, como venga del banco)
            // o por nombre del miembro.
            ->when($filtros['buscar'] ?? null, function ($q, $texto) {
                $norm = OrdenPago::normalizar($texto);
                $q->where(function ($q) use ($texto, $norm) {
                    $q->where('referencia_normalizada', 'like', "%{$norm}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$texto}%"));
                });
            })
            ->orderByRaw("estado_pago = 'generada' DESC")   // lo pendiente arriba
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (OrdenPago $o) => $this->paraLaBandeja($o));

        return Inertia::render('Caja/Ordenes', [
            'ordenes' => $ordenes,
            'filtros' => $filtros,
            'metodos' => collect(MetodoReferencia::cases())->map(fn ($m) => ['valor' => $m->value, 'etiqueta' => $m->etiqueta()]),
            'estados' => collect(EstadoPagoOrden::cases())->map(fn ($e) => ['valor' => $e->value, 'etiqueta' => $e->etiqueta()]),
        ]);
    }

    /** Fase 4.2 — confirmar el pago: activa la membresía en una sola transacción. */
    public function confirmar(Request $request, OrdenPago $orden): RedirectResponse
    {
        $datos = $request->validate([
            'fecha_pago'         => ['required', 'date', 'before_or_equal:today'],
            'monto_recibido'     => ['required', 'numeric', 'min:0'],
            'evidencia'          => ['required', 'string', 'max:191'],
            'nota'               => ['nullable', 'string', 'max:1000'],
            'encadenar'          => ['boolean'],
            'aceptar_diferencia' => ['boolean'],
        ]);

        // Monto distinto: no se confirma sin decidir explícitamente. Los pagos
        // incompletos son la fuente número uno de descuadres.
        $diferencia = abs((float) $datos['monto_recibido'] - (float) $orden->monto) > 0.005;
        if ($diferencia && ! ($datos['aceptar_diferencia'] ?? false)) {
            throw ValidationException::withMessages([
                'monto_recibido' => 'El monto recibido no coincide con el esperado ('
                    . number_format((float) $orden->monto, 2) . '). Acepta la diferencia con una nota o corrígelo.',
            ]);
        }
        if ($diferencia && blank($datos['nota'] ?? null)) {
            throw ValidationException::withMessages(['nota' => 'Escribe una nota explicando la diferencia de monto.']);
        }

        $orden = $this->confirmador->confirmar($orden, $request->user(), $datos);

        // Aviso al miembro: pago confirmado, membresía activa (Fase 6).
        try {
            $orden->user->notify(new PagoConfirmado($orden));
        } catch (\Throwable $e) {
            report($e);
        }

        // Aviso de otras órdenes abiertas del mismo miembro, para no cobrar dos veces.
        $otras = OrdenPago::where('user_id', $orden->user_id)
            ->where('estado_pago', EstadoPagoOrden::Generada)
            ->whereKeyNot($orden->id)->count();

        $msg = "Pago confirmado. Membresía de {$orden->user->name} activa.";
        if ($otras > 0) {
            return back()->with('warning', $msg . " Ojo: {$orden->user->name} tiene {$otras} referencia(s) más sin pagar; revísalas para no cobrar doble.");
        }

        return back()->with('success', $msg);
    }

    /** Fase 4.3 — cancelar con motivo (las órdenes no se borran). */
    public function cancelar(Request $request, OrdenPago $orden): RedirectResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'max:191']]);

        if (! in_array($orden->estado_pago, [EstadoPagoOrden::Generada, EstadoPagoOrden::Vencida], true)) {
            return back()->with('error', 'Solo se cancela una orden pendiente o vencida.');
        }

        $orden->update(['estado_pago' => EstadoPagoOrden::Cancelada, 'motivo_cancelacion' => $datos['motivo']]);

        return back()->with('info', 'Orden cancelada.');
    }

    /** Fase 4.3 — regenerar desde una vencida, con el precio del día. */
    public function regenerar(Request $request, OrdenPago $orden): RedirectResponse
    {
        if ($orden->estado_pago !== EstadoPagoOrden::Vencida) {
            return back()->with('error', 'Solo se regenera una orden vencida.');
        }

        $plan = $orden->plan;
        $ref  = OrdenPago::nuevaReferencia();
        $dias = (int) config('nodico.pagos_referencia.vencimiento_dias', 7);

        $nueva = new OrdenPago([
            'referencia'             => $ref['referencia'],
            'referencia_normalizada' => $ref['referencia_normalizada'],
            'user_id'                => $orden->user_id,
            'plan_id'                => $plan->id,
            'monto'                  => (float) $plan->precio,   // precio del día
            'metodo'                 => $orden->metodo,
            'pide_factura'           => $orden->pide_factura,
            'estado_pago'            => EstadoPagoOrden::Generada,
            'estado_factura'         => $orden->pide_factura ? \App\Enums\EstadoFacturaOrden::Solicitada : \App\Enums\EstadoFacturaOrden::NoSolicitada,
            'vence_el'               => now()->addDays($dias)->endOfDay(),
        ]);
        if ($orden->pide_factura) {
            $nueva->copiarFiscalesDe($orden->user->datosFiscales);
        }
        $nueva->save();

        return back()->with('success', "Referencia regenerada: {$nueva->referencia} (precio del día).");
    }

    // ── Fase 5 · Facturas ────────────────────────────────────────────────────

    /** Bandeja de facturas por emitir: confirmadas que pidieron factura y no la tienen. */
    public function facturas(Request $request): Response
    {
        $ordenes = OrdenPago::query()
            ->with(['user:id,name', 'plan:id,nombre'])
            ->where('estado_pago', EstadoPagoOrden::Confirmada)
            ->whereIn('estado_factura', [EstadoFacturaOrden::Solicitada, EstadoFacturaOrden::Emitida])
            ->orderBy('confirmada_en')
            ->paginate(25)
            ->through(fn (OrdenPago $o) => [
                'id'         => $o->id,
                'referencia' => $o->referencia,
                'miembro'    => $o->user?->name,
                'concepto'   => $o->plan?->nombre,
                'monto'      => (float) $o->monto,
                'forma_pago' => $o->metodo->etiqueta(),   // el CFDI tiene que reflejarla
                'estado_factura' => $o->estado_factura->value,
                'estado_factura_label' => $o->estado_factura->etiqueta(),
                'confirmada' => $o->confirmada_en?->toIso8601String(),
                'fiscal'     => [
                    'rfc' => $o->fiscal_rfc, 'razon_social' => $o->fiscal_razon_social,
                    'regimen' => $o->fiscal_regimen, 'uso_cfdi' => $o->fiscal_uso_cfdi,
                    'cp' => $o->fiscal_cp, 'email' => $o->fiscal_email,
                ],
            ]);

        return Inertia::render('Caja/Facturas', ['ordenes' => $ordenes]);
    }

    /** Contabilidad sube el PDF y el XML + folio fiscal → la orden pasa a `emitida`. */
    public function subirFactura(Request $request, OrdenPago $orden): RedirectResponse
    {
        if ($orden->estado_pago !== EstadoPagoOrden::Confirmada || ! $orden->pide_factura) {
            return back()->with('error', 'Esta orden no espera factura.');
        }

        $request->validate([
            'folio_fiscal' => ['required', 'string', 'max:64'],
            'pdf'          => ['required', 'file', 'mimetypes:application/pdf', 'max:10240'],
            'xml'          => ['required', 'file', 'mimetypes:application/xml,text/xml', 'max:10240'],
        ]);

        $pdf = $request->file('pdf')->store("facturas/{$orden->id}", 'local');
        $xml = $request->file('xml')->store("facturas/{$orden->id}", 'local');

        $orden->update([
            'folio_fiscal'       => $request->string('folio_fiscal'),
            'factura_pdf'        => $pdf,
            'factura_xml'        => $xml,
            'factura_emitida_en' => now(),
            'estado_factura'     => EstadoFacturaOrden::Emitida,
        ]);

        return back()->with('success', 'Factura cargada. Ya puedes enviársela al miembro.');
    }

    /** Notificar al miembro → la orden pasa a `enviada`. */
    public function enviarFactura(Request $request, OrdenPago $orden): RedirectResponse
    {
        if ($orden->estado_factura !== EstadoFacturaOrden::Emitida) {
            return back()->with('error', 'Primero sube el PDF y el XML de la factura.');
        }

        $orden->update(['estado_factura' => EstadoFacturaOrden::Enviada, 'factura_enviada_en' => now()]);

        // Aviso en el portal (además del correo): le aparece en su tablero y en la
        // campana de avisos, con enlace mental a «Mis facturas».
        $folio = $orden->folio_fiscal ? " (folio {$orden->folio_fiscal})" : '';
        Comunicado::create([
            'user_id' => $orden->user_id,
            'titulo'  => 'Te llegó una factura',
            'mensaje' => "Ya emitimos tu factura de {$orden->plan?->nombre}{$folio}. La enviamos a tu correo y puedes descargarla cuando quieras en «Mis facturas».",
            'tipo'    => 'pago',
            'leido'   => false,
        ]);

        try {
            $orden->user->notify(new FacturaEmitida($orden));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Factura enviada al miembro (correo + aviso en su portal).');
    }

    /** Export para el cierre del mes (CSV con fórmulas neutralizadas). */
    public function exportarFacturas(): StreamedResponse
    {
        $ordenes = OrdenPago::query()
            ->where('pide_factura', true)
            ->whereIn('estado_factura', [EstadoFacturaOrden::Solicitada, EstadoFacturaOrden::Emitida, EstadoFacturaOrden::Enviada])
            ->with(['user:id,name', 'plan:id,nombre'])
            ->orderByDesc('confirmada_en')->get();

        return response()->streamDownload(function () use ($ordenes) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Referencia', 'Miembro', 'Concepto', 'Monto', 'Forma de pago', 'RFC', 'Razon social', 'Regimen', 'Uso CFDI', 'CP', 'Folio fiscal', 'Estado', 'Confirmada']);
            foreach ($ordenes as $o) {
                fputcsv($out, array_map([$this, 'celdaCsv'], [
                    $o->referencia, $o->user?->name, $o->plan?->nombre, number_format((float) $o->monto, 2, '.', ''),
                    $o->metodo->etiqueta(), $o->fiscal_rfc, $o->fiscal_razon_social, $o->fiscal_regimen,
                    $o->fiscal_uso_cfdi, $o->fiscal_cp, $o->folio_fiscal, $o->estado_factura->etiqueta(),
                    $o->confirmada_en?->toDateString(),
                ]));
            }
            fclose($out);
        }, 'facturas-nodico-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutraliza fórmulas: una celda que empieza con = + - @ no se ejecuta en Excel. */
    private function celdaCsv($valor): string
    {
        $valor = (string) $valor;
        return preg_match('/^[=+\-@]/', $valor) === 1 ? "'" . $valor : $valor;
    }

    // ── Interno ──────────────────────────────────────────────────────────────

    private function paraLaBandeja(OrdenPago $o): array
    {
        return [
            'id'             => $o->id,
            'referencia'     => $o->referencia,
            'miembro'        => $o->user?->name,
            'plan'           => $o->plan?->nombre,
            'monto'          => (float) $o->monto,
            'metodo'         => $o->metodo->value,
            'metodo_label'   => $o->metodo->etiqueta(),
            'estado_pago'    => $o->estado_pago->value,
            'estado_pago_label' => $o->estado_pago->etiqueta(),
            'estado_factura' => $o->estado_factura->value,
            'pide_factura'   => (bool) $o->pide_factura,
            'reportado'      => (bool) $o->reportado_pagado_en,
            'vence_el'       => $o->vence_el?->toIso8601String(),
            'por_vencer'     => $o->estado_pago === EstadoPagoOrden::Generada
                && $o->vence_el && $o->vence_el->isBetween(now(), now()->addDays(2)),
            'creada'         => $o->created_at?->toIso8601String(),
        ];
    }
}
