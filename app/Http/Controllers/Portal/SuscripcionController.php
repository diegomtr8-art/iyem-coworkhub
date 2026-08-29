<?php

namespace App\Http\Controllers\Portal;

use App\Enums\BolsaDeHoras;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResuelveLaMembresia;
use App\Models\Plane;
use App\Models\Suscripcion;
use App\Servicios\Horas\ResumenDeBolsas;
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
        ]);
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
