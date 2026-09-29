<?php

namespace App\Http\Controllers\Api\Movil;

use App\Enums\EstadoFacturaOrden;
use App\Enums\EstadoPagoOrden;
use App\Enums\MetodoReferencia;
use App\Exceptions\ErrorDeApi;
use App\Http\Controllers\RegresoDelBancoAppController;
use App\Models\CargoPasarela;
use App\Models\Factura;
use App\Models\OrdenPago;
use App\Models\Plane;
use App\Servicios\Membresias\DescripcionDelPlan;
use App\Servicios\Membresias\MembresiaDelMiembro;
use App\Servicios\Pagos\Contratos\PasarelaDePagos;
use App\Servicios\Pagos\GeneradorDeReferencia;
use App\Servicios\Pagos\PasarelaStripe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pagos, facturas y contratar desde la app (docs/API-MOVIL.md §6.7).
 *
 * Regla de siempre: **la app nunca activa nada**. La membresía la activa el
 * proveedor al confirmar el pago con tarjeta (webhook de Stripe, o la consulta
 * del cargo a la API de BBVA) o caja al confirmar (referencia). Aquí solo se
 * prepara el cobro y se consulta.
 */
class PagosController extends ControladorMovil
{
    public function __construct(
        private readonly PasarelaDePagos $tarjeta,
        private readonly GeneradorDeReferencia $referencias,
    ) {
    }

    // ── Contratar ───────────────────────────────────────────────────────────

    public function contratables(Request $request, DescripcionDelPlan $descripcion, MembresiaDelMiembro $membresias): JsonResponse
    {
        $usuario = $request->user();
        $actual  = $membresias->vigente($usuario);
        $propia  = $actual && $membresias->esTitular($usuario, $actual) ? $actual : null;

        return $this->datos([
            'planes' => Plane::publicos()->get()->map(function (Plane $plan) use ($descripcion, $propia) {
                $datos = $descripcion->para($plan);
                unset($datos['stripe_url']);

                return [
                    ...$datos,
                    'recurrente'  => (bool) $plan->cobro_recurrente,
                    'es_el_actual' => $propia?->plan_id === $plan->id,
                    'tarjeta_disponible' => $this->tarjeta->disponiblePara($plan),
                    // Con BBVA un plan recurrente se paga por periodo. Con
                    // Openpay, la app todavía paga con el formulario de
                    // Openpay (sin guardar tarjeta): la renovación automática
                    // se contrata desde la web hasta el paso 6.
                    'renueva_sola' => $this->tarjeta->renuevaSola($plan)
                        && ! $this->tarjeta instanceof \App\Servicios\Pagos\PasarelaOpenpay,
                ];
            })->values(),
            'metodos' => [
                'tarjeta'    => $this->tarjeta->disponible(),
                'pasarela'   => $this->tarjeta->nombre(),
                'referencia' => collect(MetodoReferencia::cases())->map(fn (MetodoReferencia $m) => [
                    'valor'    => $m->value,
                    'etiqueta' => $m->etiqueta(),
                ])->values(),
            ],
            'tiene_datos_fiscales' => $this->referencias->datosFiscalesCompletos($usuario),
            'vencimiento_dias'     => (int) config('nodico.pagos_referencia.vencimiento_dias', 7),
        ]);
    }

    /**
     * Prepara el cobro con tarjeta.
     *
     * - Stripe: los datos para la hoja de pago nativa (`client_secret`).
     * - BBVA: `modo: redireccion` y la `url` del formulario del banco, que la
     *   app abre en el navegador del sistema; BBVA regresa a
     *   `pago/banco/regreso-app`, que devuelve a la app por `vuelta`.
     */
    public function prepararTarjeta(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:planes,id'],
            'vuelta'  => ['nullable', 'string', 'max:500'],
        ]);
        $plan  = $this->planPublico($datos['plan_id']);

        $intent = $this->tarjeta->preparar($request->user(), $plan, true, 'app');

        if (($intent['modo'] ?? null) === 'redireccion') {
            CargoPasarela::whereKey($intent['cargo_id'])
                ->update(['url_vuelta' => RegresoDelBancoAppController::vueltaValida($datos['vuelta'] ?? null)]);
        }

        return $this->datos([
            ...$intent,
            'pasarela'        => $this->tarjeta->nombre(),
            'llave_publica'   => $this->tarjeta->llavePublica(),
            'nombre_comercio' => 'Nódico',
            'plan'            => ['id' => $plan->id, 'nombre' => $plan->nombre, 'precio' => (float) $plan->precio],
        ]);
    }

    /**
     * Plan recurrente: con la tarjeta ya guardada por la hoja de pago, crear la
     * suscripción. Si el banco pide autenticarse, se devuelve el `client_secret`
     * para que la app lo resuelva.
     */
    public function suscribir(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'plan_id'         => ['required', 'integer', 'exists:planes,id'],
            'setup_intent_id' => ['required', 'string', 'starts_with:seti_', 'max:255'],
        ]);

        $plan    = $this->planPublico($datos['plan_id']);
        $usuario = $request->user();

        // La hoja de pago con SetupIntent es de Stripe. Con otra pasarela el
        // periodo se paga con `pagos/tarjeta`.
        if (! $this->tarjeta instanceof PasarelaStripe) {
            throw new ErrorDeApi(409, 'no_disponible', 'La renovación automática no está disponible con el pago actual. Paga el periodo desde «Contratar».');
        }

        if (! $plan->cobro_recurrente) {
            throw ValidationException::withMessages(['plan_id' => 'Ese plan se paga en una sola exhibición.']);
        }

        // Antes de tocar Stripe: si ya hay una suscripción, se retoma su pago
        // pendiente o se rechaza. Nunca se crea una segunda.
        $pendiente = $this->tarjeta->suscripcionEnCurso($usuario);

        if ($pendiente === false) {
            $metodo    = $this->tarjeta->metodoDelSetupIntent($usuario, $datos['setup_intent_id']);
            $pendiente = $this->tarjeta->suscribir($usuario, $plan, $metodo);
        }

        return $this->datos([
            'requiere_accion' => $pendiente !== null,
            'client_secret'   => $pendiente,
            'message'         => 'Estamos confirmando tu pago. En cuanto el banco lo apruebe, se activa tu membresía.',
        ]);
    }

    /** La app pregunta hasta que el proveedor confirma (con BBVA, por el id del cargo). */
    public function estadoTarjeta(Request $request): JsonResponse
    {
        return $this->datos($this->tarjeta->estadoDelCobro($request->user(), $request->integer('cargo_id') ?: null));
    }

    /** Referencia para transferencia o efectivo. */
    public function referencia(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'plan_id'      => ['required', 'integer', 'exists:planes,id'],
            'metodo'       => ['required', Rule::enum(MetodoReferencia::class)],
            'pide_factura' => ['required', 'boolean'],
        ]);

        $usuario = $request->user();

        if ($datos['pide_factura'] && ! $this->referencias->datosFiscalesCompletos($usuario)) {
            throw new ErrorDeApi(422, 'datos_fiscales_incompletos',
                'Para pedir factura necesitas completar tus datos fiscales. Complétalos y vuelve a generar tu referencia.');
        }

        $orden = $this->referencias->generar(
            $usuario,
            $this->planPublico($datos['plan_id']),
            MetodoReferencia::from($datos['metodo']),
            (bool) $datos['pide_factura'],
        );

        return $this->datos([
            'orden'           => $this->orden($orden->load('plan')),
            'datos_bancarios' => $this->referencias->datosBancarios($orden),
            'message'         => 'Te mandamos la referencia por correo. En cuanto contabilidad confirme tu pago, se activa tu membresía.',
        ], 201);
    }

    // ── Historial ───────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $pagina = $request->user()->ordenesPago()
            ->with('plan:id,nombre')
            ->orderByDesc('id')
            ->cursorPaginate(20);

        return $this->pagina($pagina, fn (OrdenPago $o) => $this->orden($o));
    }

    public function mostrar(Request $request, int $id): JsonResponse
    {
        $orden = $this->ordenPropia($request, $id);

        return $this->datos([
            ...$this->orden($orden->load('plan')),
            'datos_bancarios' => $this->referencias->datosBancarios($orden),
        ]);
    }

    /** «Ya pagué»: no confirma nada, solo sube la orden en la bandeja de caja. */
    public function yaPague(Request $request, int $id): JsonResponse
    {
        $orden = $this->ordenPropia($request, $id);

        if ($orden->estado_pago === EstadoPagoOrden::Generada && ! $orden->reportado_pagado_en) {
            $orden->update(['reportado_pagado_en' => now()]);
        }

        return $this->datos([
            'orden'   => $this->orden($orden->refresh()->load('plan')),
            'message' => 'Avisamos a contabilidad. En cuanto confirmen tu pago, se activa tu membresía.',
        ]);
    }

    /** Los cargos que registra el panel (tabla `facturas`). */
    public function cobros(Request $request): JsonResponse
    {
        $pagina = $request->user()->facturas()
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->cursorPaginate(20);

        return $this->pagina($pagina, fn (Factura $f) => [
            'id'          => $f->id,
            'folio'       => $f->folio,
            'concepto'    => $f->concepto,
            'fecha'       => $f->fecha?->toDateString(),
            'total'       => (float) $f->total,
            'estatus'     => $f->estatus,
            'metodo_pago' => $f->metodo_pago,
            'fecha_pago'  => $f->fecha_pago?->toDateString(),
        ]);
    }

    /** Solo las facturas que contabilidad ya emitió, con su PDF. */
    public function facturas(Request $request): JsonResponse
    {
        return $this->datos($request->user()->ordenesPago()
            ->whereIn('estado_factura', [EstadoFacturaOrden::Emitida->value, EstadoFacturaOrden::Enviada->value])
            ->whereNotNull('factura_pdf')
            ->with('plan:id,nombre')
            ->orderByDesc('factura_emitida_en')
            ->get()
            ->map(fn (OrdenPago $o) => [
                'id'           => $o->id,
                'referencia'   => $o->referencia,
                'plan'         => $o->plan?->nombre,
                'monto'        => (float) $o->monto,
                'folio_fiscal' => $o->folio_fiscal,
                'emitida_en'   => $o->factura_emitida_en?->toIso8601String(),
                'tiene_xml'    => (bool) $o->factura_xml,
            ])
            ->values());
    }

    public function descargar(Request $request, int $id, string $formato): StreamedResponse
    {
        $orden = $this->ordenPropia($request, $id);
        $ruta  = $formato === 'xml' ? $orden->factura_xml : $orden->factura_pdf;

        abort_if(! $ruta || ! Storage::disk('local')->exists($ruta), 404);

        return Storage::disk('local')->download($ruta, "factura-{$orden->referencia}.{$formato}");
    }

    // ── Apoyo ───────────────────────────────────────────────────────────────

    /** 404 si no existe o no es tuya. */
    private function ordenPropia(Request $request, int $id): OrdenPago
    {
        $orden = $request->user()->ordenesPago()->whereKey($id)->first();

        abort_unless($orden, 404);

        return $orden;
    }

    private function planPublico(int $id): Plane
    {
        $plan = Plane::publicos()->whereKey($id)->first();

        if (! $plan) {
            throw ValidationException::withMessages(['plan_id' => 'Ese plan ya no está disponible.']);
        }

        return $plan;
    }

    private function orden(OrdenPago $o): array
    {
        return [
            'id'                      => $o->id,
            'referencia'              => $o->referencia,
            'plan'                    => $o->plan?->nombre,
            'monto'                   => (float) $o->monto,
            'metodo'                  => $o->metodo->value,
            'metodo_etiqueta'         => $o->metodo->etiqueta(),
            'estado_pago'             => $o->estado_pago->value,
            'estado_pago_etiqueta'    => $o->estado_pago->etiqueta(),
            'estado_factura'          => $o->estado_factura->value,
            'estado_factura_etiqueta' => $o->estado_factura->etiqueta(),
            'pide_factura'            => (bool) $o->pide_factura,
            'vence_el'                => $o->vence_el?->toIso8601String(),
            'reportado'               => (bool) $o->reportado_pagado_en,
            'tiene_pdf'               => (bool) $o->factura_pdf,
            'tiene_xml'               => (bool) $o->factura_xml,
            'creada'                  => $o->created_at?->toIso8601String(),
        ];
    }
}
