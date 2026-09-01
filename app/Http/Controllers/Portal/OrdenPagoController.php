<?php

namespace App\Http\Controllers\Portal;

use App\Enums\EstadoFacturaOrden;
use App\Enums\EstadoPagoOrden;
use App\Enums\MetodoReferencia;
use App\Http\Controllers\Controller;
use App\Models\DatosFiscales;
use App\Models\OrdenPago;
use App\Models\Plane;
use App\Notifications\ReferenciaGenerada;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pagos con referencia (transferencia / efectivo) — cara al miembro.
 *
 * Convive con Stripe: la elección del método va **antes** de cualquier
 * formulario, con la consecuencia escrita (tarjeta activa al instante sin
 * factura; referencia activa al confirmarse el pago, con factura). Este
 * controlador NO activa membresías: eso lo hace caja al confirmar (Fase 4).
 */
class OrdenPagoController extends Controller
{
    /** Los datos que contabilidad necesita sí o sí para poder facturar. */
    private const FISCALES_OBLIGATORIOS = ['rfc', 'razon_social', 'regimen_fiscal', 'uso_cfdi', 'codigo_postal'];

    /** Fase 2 — elegir cómo pagar, con la consecuencia de cada opción. */
    public function elegir(Request $request, Plane $plan): Response
    {
        return Inertia::render('Portal/ElegirMetodoPago', [
            'plan' => [
                'id'            => $plan->id,
                'nombre'        => $plan->nombre,
                'precio'        => (float) $plan->precio,
                'periodo_label' => $plan->periodo_label,
                'recurrente'    => (bool) $plan->cobro_recurrente,
            ],
            'tieneDatosFiscales' => $this->datosFiscalesCompletos($request->user()),
            'vencimientoDias'    => (int) config('nodico.pagos_referencia.vencimiento_dias', 7),
        ]);
    }

    /** Fase 2/3 — generar la orden con referencia y llevar a las instrucciones. */
    public function generar(Request $request, Plane $plan): RedirectResponse
    {
        $datos = $request->validate([
            'metodo'       => ['required', Rule::enum(MetodoReferencia::class)],
            'pide_factura' => ['required', 'boolean'],
        ]);

        $usuario = $request->user();
        $pideFactura = (bool) $datos['pide_factura'];
        $df = $usuario->datosFiscales;

        // Contabilidad no puede facturar sin datos fiscales completos. También se
        // valida en el servidor, no solo en la pantalla (Fase 7).
        if ($pideFactura && ! $this->datosFiscalesCompletos($usuario)) {
            return redirect()->route('portal.datos-fiscales')
                ->with('info', 'Para pedir factura necesitas completar tus datos fiscales. Complétalos y vuelve a generar tu referencia.');
        }

        $ref  = OrdenPago::nuevaReferencia();
        $dias = (int) config('nodico.pagos_referencia.vencimiento_dias', 7);

        $orden = new OrdenPago([
            'referencia'             => $ref['referencia'],
            'referencia_normalizada' => $ref['referencia_normalizada'],
            'user_id'                => $usuario->id,
            'plan_id'                => $plan->id,
            'monto'                  => (float) $plan->precio,
            'metodo'                 => MetodoReferencia::from($datos['metodo']),
            'pide_factura'           => $pideFactura,
            'estado_pago'            => EstadoPagoOrden::Generada,
            'estado_factura'         => $pideFactura ? EstadoFacturaOrden::Solicitada : EstadoFacturaOrden::NoSolicitada,
            'vence_el'               => now()->addDays($dias)->endOfDay(),
        ]);

        if ($pideFactura) {
            $orden->copiarFiscalesDe($df);   // foto fiscal, no enlace vivo
        }

        $orden->save();

        // El correo con la referencia (Fase 6). Un fallo de correo no tumba el flujo.
        try {
            $usuario->notify(new ReferenciaGenerada($orden));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('portal.referencia.mostrar', $orden);
    }

    /** Fase 3 — la referencia y sus instrucciones. */
    public function mostrar(Request $request, OrdenPago $orden): Response
    {
        $this->soloDueno($request, $orden);

        $banco = config('nodico.pagos_referencia');

        return Inertia::render('Portal/Referencia', [
            'orden'         => $this->paraLaVista($orden->load('plan')),
            'datosBancarios' => $orden->metodo === MetodoReferencia::Transferencia ? [
                'banco'        => $banco['banco'] ?? '',
                'clabe'        => $banco['clabe'] ?? '',
                'beneficiario' => $banco['beneficiario'] ?? '',
                'cuenta'       => $banco['cuenta'] ?? '',
            ] : null,
        ]);
    }

    /** Fase 3 — «ya pagué»: no confirma nada, solo lo sube en la bandeja de caja. */
    public function yaPague(Request $request, OrdenPago $orden): RedirectResponse
    {
        $this->soloDueno($request, $orden);

        if ($orden->estado_pago === EstadoPagoOrden::Generada && ! $orden->reportado_pagado_en) {
            $orden->update(['reportado_pagado_en' => now()]);
        }

        return back()->with('success', 'Avisamos a contabilidad. En cuanto confirmen tu pago, se activa tu membresía.');
    }

    /** Fase 3 — «Mis pagos»: las órdenes del miembro con ambos estados. */
    public function misPagos(Request $request): Response
    {
        $ordenes = $request->user()->ordenesPago()
            ->with('plan:id,nombre')
            ->orderByDesc('id')
            ->get()
            ->map(fn (OrdenPago $o) => $this->paraLaVista($o));

        return Inertia::render('Portal/MisPagos', ['ordenes' => $ordenes]);
    }

    /**
     * «Mis facturas»: solo las que contabilidad ya emitió (con su PDF/XML), para
     * que el miembro las descargue sin buscarlas entre todos sus pagos.
     */
    public function misFacturas(Request $request): Response
    {
        $facturas = $request->user()->ordenesPago()
            ->whereIn('estado_factura', [EstadoFacturaOrden::Emitida->value, EstadoFacturaOrden::Enviada->value])
            ->whereNotNull('factura_pdf')
            ->with('plan:id,nombre')
            ->orderByDesc('factura_emitida_en')
            ->get()
            ->map(fn (OrdenPago $o) => [
                'id'             => $o->id,
                'referencia'     => $o->referencia,
                'plan'           => $o->plan?->nombre,
                'monto'          => (float) $o->monto,
                'folio_fiscal'   => $o->folio_fiscal,
                'estado_factura' => $o->estado_factura->value,
                'estado_factura_label' => $o->estado_factura->etiqueta(),
                'emitida_en'     => $o->factura_emitida_en?->toIso8601String(),
                'enviada_en'     => $o->factura_enviada_en?->toIso8601String(),
                'tiene_xml'      => (bool) $o->factura_xml,
            ]);

        return Inertia::render('Portal/MisFacturas', ['facturas' => $facturas]);
    }

    public function descargarPdf(Request $request, OrdenPago $orden): StreamedResponse
    {
        return $this->descargarFactura($request, $orden, 'factura_pdf', 'pdf');
    }

    public function descargarXml(Request $request, OrdenPago $orden): StreamedResponse
    {
        return $this->descargarFactura($request, $orden, 'factura_xml', 'xml');
    }

    // ── Interno ──────────────────────────────────────────────────────────────

    private function descargarFactura(Request $request, OrdenPago $orden, string $campo, string $ext): StreamedResponse
    {
        $this->soloDueno($request, $orden);

        $ruta = $orden->{$campo};
        abort_if(! $ruta || ! Storage::disk('local')->exists($ruta), 404);

        return Storage::disk('local')->download($ruta, "factura-{$orden->referencia}.{$ext}");
    }

    /** Un miembro solo ve y descarga lo suyo. */
    private function soloDueno(Request $request, OrdenPago $orden): void
    {
        abort_unless($orden->user_id === $request->user()->id, 403);
    }

    private function datosFiscalesCompletos(?\App\Models\User $usuario): bool
    {
        $df = $usuario?->datosFiscales;
        if (! $df) {
            return false;
        }
        foreach (self::FISCALES_OBLIGATORIOS as $campo) {
            if (blank($df->{$campo})) {
                return false;
            }
        }
        return true;
    }

    private function paraLaVista(OrdenPago $o): array
    {
        return [
            'id'             => $o->id,
            'referencia'     => $o->referencia,
            'plan'           => $o->plan?->nombre,
            'monto'          => (float) $o->monto,
            'metodo'         => $o->metodo->value,
            'metodo_label'   => $o->metodo->etiqueta(),
            'estado_pago'    => $o->estado_pago->value,
            'estado_pago_label' => $o->estado_pago->etiqueta(),
            'estado_factura' => $o->estado_factura->value,
            'estado_factura_label' => $o->estado_factura->etiqueta(),
            'pide_factura'   => (bool) $o->pide_factura,
            'vence_el'       => $o->vence_el?->toIso8601String(),
            'reportado'      => (bool) $o->reportado_pagado_en,
            'tiene_pdf'      => (bool) $o->factura_pdf,
            'tiene_xml'      => (bool) $o->factura_xml,
            'creada'         => $o->created_at?->toIso8601String(),
        ];
    }
}
