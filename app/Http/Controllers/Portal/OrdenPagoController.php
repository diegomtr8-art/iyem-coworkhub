<?php

namespace App\Http\Controllers\Portal;

use App\Enums\EstadoFacturaOrden;
use App\Enums\EstadoPagoOrden;
use App\Enums\MetodoReferencia;
use App\Http\Controllers\Controller;
use App\Models\DatosFiscales;
use App\Models\OrdenPago;
use App\Models\Plane;
use App\Servicios\Pagos\Contratos\PasarelaDePagos;
use App\Servicios\Pagos\GeneradorDeReferencia;
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
                // Con BBVA un plan recurrente se paga por periodo.
                'renueva_sola'  => app(PasarelaDePagos::class)->renuevaSola($plan),
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

        // Sin datos fiscales completos no se puede facturar: se manda a
        // completarlos, como siempre. La regla vive en el generador.
        if ((bool) $datos['pide_factura'] && ! $this->generador()->datosFiscalesCompletos($request->user())) {
            return redirect()->route('portal.datos-fiscales')
                ->with('info', 'Para pedir factura necesitas completar tus datos fiscales. Complétalos y vuelve a generar tu referencia.');
        }

        $orden = $this->generador()->generar(
            $request->user(),
            $plan,
            MetodoReferencia::from($datos['metodo']),
            (bool) $datos['pide_factura'],
        );

        return redirect()->route('portal.referencia.mostrar', $orden);
    }

    /** Fase 3 — la referencia y sus instrucciones. */
    public function mostrar(Request $request, OrdenPago $orden): Response
    {
        $this->soloDueno($request, $orden);

        return Inertia::render('Portal/Referencia', [
            'orden'          => $this->paraLaVista($orden->load('plan')),
            'datosBancarios' => $this->generador()->datosBancarios($orden),
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
        return $this->generador()->datosFiscalesCompletos($usuario);
    }

    private function generador(): GeneradorDeReferencia
    {
        return app(GeneradorDeReferencia::class);
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
