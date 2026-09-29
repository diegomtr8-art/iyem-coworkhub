<?php

namespace App\Http\Controllers\Api\Movil;

use App\Exceptions\ErrorDeApi;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Horas\ResumenDeBolsas;
use App\Servicios\Membresias\DescripcionDelPlan;
use App\Servicios\Membresias\GestorDeAcompanante;
use App\Servicios\Membresias\MembresiaDelMiembro;
use App\Servicios\Pagos\Contratos\PasarelaDePagos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Mi membresía (docs/API-MOVIL.md §6.5).
 *
 * El acompañante de un Nodo Match ve la membresía compartida en solo lectura:
 * no gestiona al acompañante ni la renovación (decisión del 22/09/2026). Esas
 * rutas le responden `403 solo_titular`.
 */
class MembresiaController extends ControladorMovil
{
    public function __construct(
        private readonly MembresiaDelMiembro $membresias,
        private readonly DescripcionDelPlan $descripcion,
    ) {
    }

    public function mostrar(Request $request, ResumenDeBolsas $resumen): JsonResponse
    {
        $usuario     = $request->user();
        $suscripcion = $this->membresias->vigente($usuario);
        $estado      = $this->membresias->estado($usuario, $suscripcion);
        $titular     = $this->membresias->esTitular($usuario, $suscripcion);

        return $this->datos([
            'estado' => [
                'tiene'      => $estado['tiene'],
                'tono'       => $estado['tono'],
                'titulo'     => $estado['titulo'],
                'detalle'    => $estado['detalle'],
                'accion'     => $estado['accion'],
                'accion_url' => $estado['accion_href'],
            ],

            'rol_en_membresia' => $this->membresias->rol($usuario, $suscripcion),

            'titular' => $suscripcion && ! $titular ? ['nombre' => $suscripcion->user?->name] : null,

            'vigente' => $suscripcion ? [
                'id'               => $suscripcion->id,
                'fecha_inicio'     => $suscripcion->fecha_inicio->toDateString(),
                'fecha_fin'        => $suscripcion->fecha_fin->toDateString(),
                'dias_restantes'   => $this->membresias->diasRestantes($suscripcion),
                // Lo que pagó el titular es asunto del titular.
                'precio_pagado'    => $titular ? (float) $suscripcion->precio_pagado : null,
                'ciclo_inicio'     => $suscripcion->cicloInicio()->toDateString(),
                'ciclo_fin'        => $suscripcion->cicloFin()->toDateString(),
                'proximo_reinicio' => $suscripcion->proximoReinicio()?->toDateString(),
                'plan'             => $this->plan($suscripcion),
            ] : null,

            'bolsas' => $suscripcion ? $resumen->soloIncluidas($suscripcion) : [],

            'acompanante' => $suscripcion && ($suscripcion->plan?->personas ?? 1) > 1 && $titular ? [
                'admitido'   => true,
                'usuario'    => $suscripcion->companion ? [
                    'id'     => $suscripcion->companion->id,
                    'nombre' => $suscripcion->companion->name,
                    'email'  => $suscripcion->companion->email,
                ] : null,
                'face_id_ok' => (bool) $suscripcion->companion_face_id_ok,
            ] : ['admitido' => false],

            'renovacion' => $titular || ! $suscripcion ? $this->renovacion($usuario) : null,

            'historial' => $usuario->suscripciones()
                ->with('plan:id,nombre,color')
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
                ])
                ->values(),
        ]);
    }

    public function asignarAcompanante(Request $request, GestorDeAcompanante $gestor): JsonResponse
    {
        $this->soloTitular($request->user());

        $datos     = $request->validate(['email' => ['required', 'email']]);
        $companion = $this->generalA422(fn () => $gestor->asignar($request->user(), $datos['email']));

        return $this->datos([
            'acompanante' => ['id' => $companion->id, 'nombre' => $companion->name, 'email' => $companion->email],
            'message'     => "Listo: {$companion->name} quedó como tu acompañante. Falta que pase por recepción a registrar su Face ID.",
        ]);
    }

    public function quitarAcompanante(Request $request, GestorDeAcompanante $gestor): JsonResponse
    {
        $this->soloTitular($request->user());

        $this->generalA422(fn () => $gestor->quitar($request->user()));

        return $this->datos(['message' => 'Quitamos al acompañante de tu membresía.']);
    }

    /** Cancela la renovación automática: sigue con acceso hasta el fin del periodo. */
    public function cancelarRenovacion(Request $request): JsonResponse
    {
        $this->soloTitular($request->user());

        if (! $this->pasarela()->cancelarRenovacion($request->user())) {
            return $this->datos(['message' => 'Tu membresía ya no tiene renovación automática.']);
        }

        return $this->datos(['message' => 'Cancelamos la renovación. Sigues con acceso hasta el fin del periodo que ya pagaste.']);
    }

    public function reactivarRenovacion(Request $request): JsonResponse
    {
        $this->soloTitular($request->user());

        if (! $this->pasarela()->reactivarRenovacion($request->user())) {
            throw new ErrorDeApi(409, 'no_reactivable', 'No se puede reactivar: el periodo ya terminó. Contrata de nuevo.');
        }

        return $this->datos(['message' => 'Reactivamos la renovación automática.']);
    }

    private function plan(Suscripcion $suscripcion): ?array
    {
        $plan = $this->descripcion->para($suscripcion->plan);

        if (! $plan) {
            return null;
        }

        unset($plan['stripe_url']);

        return $plan;
    }

    private function renovacion(User $usuario): array
    {
        return [
            'cobro_en_linea' => $this->pasarela()->disponible(),
            ...$this->pasarela()->estadoDeRenovacion($usuario),
        ];
    }

    private function pasarela(): PasarelaDePagos
    {
        return app(PasarelaDePagos::class);
    }

    /** @throws ErrorDeApi */
    private function soloTitular(User $usuario): void
    {
        $suscripcion = $this->membresias->vigente($usuario);

        if ($suscripcion && ! $this->membresias->esTitular($usuario, $suscripcion)) {
            throw new ErrorDeApi(403, 'solo_titular', 'Eso solo lo puede hacer '
                . ($suscripcion->user?->name ?? 'el titular') . ', titular de la membresía.');
        }
    }

    /** Los errores `general` del gestor van a la app como mensaje, no como campo. */
    private function generalA422(callable $accion): mixed
    {
        try {
            return $accion();
        } catch (ValidationException $e) {
            $general = $e->errors()['general'][0] ?? null;

            if ($general === null) {
                throw $e;
            }

            throw new ErrorDeApi(422, 'no_permitido', $general);
        }
    }
}
