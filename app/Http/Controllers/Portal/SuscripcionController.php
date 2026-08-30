<?php

namespace App\Http\Controllers\Portal;

use App\Enums\BolsaDeHoras;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResuelveLaMembresia;
use App\Models\Plane;
use App\Models\Suscripcion;
use App\Servicios\Horas\ResumenDeBolsas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'acompanante' => $suscripcion && ($suscripcion->plan?->personas ?? 1) > 1 ? [
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
     * El plan con lo que incluye ya desglosado en frases.
     *
     * Se arma en el servidor porque las reglas de qué significa `null` en cada
     * campo viven en `BolsaDeHoras` (ver `Plane`), y traducirlas en el front
     * sería una segunda copia de esa semántica.
     */
    private function planParaLaVista(?Plane $plan): ?array
    {
        if (! $plan) {
            return null;
        }

        $incluye = [];

        $incluye[] = $plan->esIlimitado()
            ? 'Acceso ilimitado al coworking'
            : $plan->dias_cowork_mes . ' día' . ($plan->dias_cowork_mes === 1 ? '' : 's') . ' de coworking';

        foreach ([BolsaDeHoras::Sala, BolsaDeHoras::Contenido, BolsaDeHoras::Asesoria] as $bolsa) {
            $cupo = $bolsa->cupo($plan);

            if ($cupo === null) {
                continue;
            }

            $tope  = $bolsa->topeDiario($plan);
            $texto = rtrim(rtrim(number_format($cupo, 2, '.', ''), '0'), '.')
                . ' h de ' . mb_strtolower($bolsa->etiqueta())
                . ($plan->tieneCiclosMensuales() ? ' al mes' : '');

            if ($tope !== null) {
                $texto .= ' (máximo ' . rtrim(rtrim(number_format($tope, 2, '.', ''), '0'), '.') . ' h al día)';
            }

            $incluye[] = $texto;
        }

        if (($plan->personas ?? 1) > 1) {
            $incluye[] = 'Para ' . $plan->personas . ' personas, con bolsa de horas compartida';
        }

        return [
            'id'            => $plan->id,
            'nombre'        => $plan->nombre,
            'subtitulo'     => $plan->subtitulo,
            'precio'        => (float) $plan->precio,
            'periodo_label' => $plan->periodo_label,
            'color'         => $plan->color,
            'personas'      => $plan->personas ?? 1,
            'stripe_url'    => $plan->stripe_url,
            'incluye'       => $incluye,

            // Los beneficios editoriales del sitio público, si los tiene.
            'beneficios'    => $plan->beneficios ?? [],
        ];
    }
}
