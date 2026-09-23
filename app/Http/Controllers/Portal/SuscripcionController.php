<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResuelveLaMembresia;
use App\Models\Plane;
use App\Models\Suscripcion;
use App\Servicios\Horas\ResumenDeBolsas;
use App\Servicios\Membresias\DescripcionDelPlan;
use App\Servicios\Membresias\GestorDeAcompanante;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Mi membresía (Fase 2.4).
 *
 * Qué incluye el plan **desglosado**, vigencia, historial y el camino para
 * renovar o cambiar. En Nodo Match, además, la gestión del acompañante.
 */
class SuscripcionController extends Controller
{
    use ResuelveLaMembresia;

    public function __construct(private readonly ResumenDeBolsas $resumen)
    {
    }

    public function index(Request $request)
    {
        $usuario     = $request->user();
        $suscripcion = $this->membresiaVigente($usuario);

        return Inertia::render('Portal/MiMembresia', [
            'estadoMembresia' => $this->estadoDeLaMembresia($usuario, $suscripcion),

            'suscripcion' => $suscripcion ? [
                'id'             => $suscripcion->id,
                'fecha_inicio'   => $suscripcion->fecha_inicio->toDateString(),
                'fecha_fin'      => $suscripcion->fecha_fin->toDateString(),
                'dias_restantes' => $this->diasRestantes($suscripcion),
                'precio_pagado'  => (float) $suscripcion->precio_pagado,
                'auto_renovar'   => (bool) $suscripcion->auto_renovar,
                'ciclo_inicio'   => $suscripcion->cicloInicio()->toDateString(),
                'ciclo_fin'      => $suscripcion->cicloFin()->toDateString(),
                'proximo_reinicio' => $suscripcion->proximoReinicio()?->toDateString(),
                'plan'           => $this->planParaLaVista($suscripcion->plan),
            ] : null,

            'medidores' => $suscripcion ? $this->resumen->soloIncluidas($suscripcion) : [],

            // Nodo Match: el acompañante.
            // Solo el titular gestiona al acompañante; el acompañante ve la
            // membresía compartida, pero no este bloque (22/09/2026).
            'acompanante' => $suscripcion && ($suscripcion->plan?->personas ?? 1) > 1
                && $suscripcion->user_id === $usuario->id ? [
                'admitido'     => true,
                'usuario'      => $suscripcion->companion?->only(['id', 'name', 'email']),
                'face_id_ok'   => (bool) $suscripcion->companion_face_id_ok,
                'bolsa_compartida' => true,
            ] : ['admitido' => false],

            'historial' => $usuario->suscripciones()
                ->with('plan:id,nombre,color,periodo_label')
                ->when($suscripcion, fn ($q) => $q->whereKeyNot($suscripcion->id))
                ->orderByDesc('fecha_inicio')
                ->limit(12)
                ->get()
                ->map(fn (Suscripcion $s) => [
                    'id'            => $s->id,
                    'plan'          => $s->plan?->nombre,
                    'color'         => $s->plan?->color,
                    'fecha_inicio'  => $s->fecha_inicio->toDateString(),
                    'fecha_fin'     => $s->fecha_fin->toDateString(),
                    'estatus'       => $s->estatus,
                    'precio_pagado' => (float) $s->precio_pagado,
                ]),

            // Para el botón de cambiar de plan.
            'otrosPlanes' => Plane::publicos()
                ->when($suscripcion?->plan_id, fn ($q, $id) => $q->whereKeyNot($id))
                ->get()
                ->map(fn (Plane $plan) => $this->planParaLaVista($plan)),

            // Fase 4.A — cobro y renovación. Solo datos locales (los que Cashier
            // guarda en `users`); los cobros de Stripe se piden aparte, para no
            // llamar a su API en cada carga del portal.
            'facturacion' => $this->datosDeFacturacion($usuario),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosDeFacturacion($usuario): array
    {
        $stripe = $usuario->subscription('default');

        return [
            // El cobro en línea está disponible si hay claves y el sitio tiene
            // configurado el precio de Stripe.
            'cobro_en_linea'      => (bool) config('cashier.key'),
            'metodo_pago'         => $usuario->pm_last_four
                ? ['marca' => $usuario->pm_type, 'ultimos4' => $usuario->pm_last_four]
                : null,
            // Estado de la suscripción recurrente en Stripe, si la hay.
            'tiene_recurrente'    => (bool) $stripe,
            'renovacion_activa'   => $stripe ? $stripe->active() && ! $stripe->canceled() : false,
            'en_periodo_de_gracia' => $stripe ? $stripe->onGracePeriod() : false,
        ];
    }

    /** A.4 — cancelar la renovación: sigue con acceso hasta el fin del periodo. */
    public function cancelarRenovacion(Request $request): RedirectResponse
    {
        $stripe = $request->user()->subscription('default');

        if (! $stripe || $stripe->canceled()) {
            return back()->with('info', 'Tu membresía ya no tiene renovación automática.');
        }

        $stripe->cancel();

        return back()->with('success', 'Cancelamos la renovación. Sigues con acceso hasta el fin del periodo que ya pagaste.');
    }

    /** A.4 — reactivar la renovación mientras siga dentro del periodo pagado. */
    public function reactivarRenovacion(Request $request): RedirectResponse
    {
        $stripe = $request->user()->subscription('default');

        if (! $stripe || ! $stripe->onGracePeriod()) {
            return back()->with('error', 'No se puede reactivar: el periodo ya terminó. Contrata de nuevo.');
        }

        $stripe->resume();

        return back()->with('success', 'Reactivamos la renovación automática.');
    }

    /**
     * Nodo Match — asignar al acompañante por su correo. Las reglas viven en
     * `GestorDeAcompanante`, que también usa la app.
     */
    public function asignarAcompanante(Request $request): RedirectResponse
    {
        $datos = $request->validate(['email' => ['required', 'email']]);

        return $this->comoAviso(function () use ($request, $datos) {
            $companion = app(GestorDeAcompanante::class)->asignar($request->user(), $datos['email']);

            return back()->with(
                'success',
                "Listo: {$companion->name} quedó como tu acompañante. Falta que pase por recepción a registrar su Face ID.",
            );
        });
    }

    /** Nodo Match — quitar al acompañante. */
    public function quitarAcompanante(Request $request): RedirectResponse
    {
        return $this->comoAviso(function () use ($request) {
            app(GestorDeAcompanante::class)->quitar($request->user());

            return back()->with('info', 'Quitamos al acompañante de tu membresía.');
        });
    }

    /**
     * Los errores generales del gestor (plan sin acompañante, no eres el
     * titular) se enseñan como aviso de la página, como siempre; los del campo
     * de correo, junto al campo.
     */
    private function comoAviso(callable $accion): RedirectResponse
    {
        try {
            return $accion();
        } catch (ValidationException $e) {
            $general = $e->errors()['general'][0] ?? null;

            if ($general === null) {
                throw $e;
            }

            return back()->with('error', $general);
        }
    }

    private function planParaLaVista(?Plane $plan): ?array
    {
        return app(DescripcionDelPlan::class)->para($plan);
    }
}
