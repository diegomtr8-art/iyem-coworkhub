<?php

namespace App\Http\Controllers\Api\Movil;

use App\Enums\EstadoAsesoria;
use App\Http\Resources\Movil\ReservaMovil;
use App\Models\AnuncioCoworking;
use App\Models\Reserva;
use App\Servicios\Horas\ResumenDeBolsas;
use App\Servicios\Membresias\MembresiaDelMiembro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inicio: todo lo de la pantalla principal en una sola llamada
 * (docs/API-MOVIL.md §6.2). En el teléfono cada viaje de red cuesta.
 *
 * Misma regla de oro que el portal web: la persona tiene que poder responder
 * sin pensar «¿cuánto me queda y hasta cuándo?». Por eso las bolsas salen de
 * `ResumenDeBolsas`, la misma fuente que los medidores de la web.
 */
class InicioController extends ControladorMovil
{
    public function __invoke(Request $request, MembresiaDelMiembro $membresias, ResumenDeBolsas $resumen): JsonResponse
    {
        $usuario     = $request->user();
        $suscripcion = $membresias->vigente($usuario);
        $estado      = $membresias->estado($usuario, $suscripcion);

        $proxima = $usuario->reservas()
            ->with('espacio')
            ->confirmadas()
            ->where('fecha', '>=', \App\Models\Reserva::hoyYmd())
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get()
            // Una reserva de hoy que ya terminó no es la «próxima».
            ->first(fn (Reserva $reserva) => $reserva->finEnCalendario()->isFuture());

        return $this->datos([
            'estado_membresia' => [
                'tiene'      => $estado['tiene'],
                'tono'       => $estado['tono'],
                'titulo'     => $estado['titulo'],
                'detalle'    => $estado['detalle'],
                'accion'     => $estado['accion'],
                'accion_url' => $estado['accion_href'],
            ],

            'membresia' => $suscripcion ? [
                'id'               => $suscripcion->id,
                'rol_en_membresia' => $membresias->rol($usuario, $suscripcion),
                'plan'             => $suscripcion->plan?->only(['id', 'nombre', 'color']),
                'fecha_fin'        => $suscripcion->fecha_fin->toDateString(),
                'dias_restantes'   => $membresias->diasRestantes($suscripcion),
                'ciclo_inicio'     => $suscripcion->cicloInicio()->toDateString(),
                'ciclo_fin'        => $suscripcion->cicloFin()->toDateString(),
            ] : null,

            'bolsas' => $suscripcion ? $resumen->para($suscripcion) : [],

            'proxima_reserva' => $proxima ? (new ReservaMovil($proxima))->resolve() : null,

            'asesorias_pendientes' => $usuario->asesorias()
                ->whereIn('estado', [EstadoAsesoria::Solicitada->value, EstadoAsesoria::Confirmada->value])
                ->count(),

            'avisos' => AnuncioCoworking::where('activo', true)
                ->where('fecha_inicio', '<=', \App\Models\Reserva::hoyYmd())
                ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', \App\Models\Reserva::hoyYmd()))
                ->orderByDesc('fecha_inicio')
                ->limit(3)
                ->get()
                ->map(fn (AnuncioCoworking $a) => [
                    'id'        => $a->id,
                    'titulo'    => $a->titulo,
                    'contenido' => $a->contenido,
                    'tipo'      => $a->tipo,
                    'desde'     => $a->fecha_inicio?->toDateString(),
                ])
                ->values(),

            'comunicados_sin_leer' => $usuario->comunicados()->where('leido', false)->count(),

            'face_id_pendiente' => ! $usuario->face_id_ok,
        ]);
    }
}
