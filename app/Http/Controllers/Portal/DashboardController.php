<?php

namespace App\Http\Controllers\Portal;

use App\Enums\EstadoAsesoria;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResuelveLaMembresia;
use App\Models\AnuncioCoworking;
use App\Models\Reserva;
use App\Servicios\Horas\ResumenDeBolsas;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Inicio del portal del miembro (Fase 2.1).
 *
 * Regla de oro de esta pantalla: **la persona debe poder responder sin pensar
 * «¿cuánto me queda y hasta cuándo?»**. De ahí el orden de lo que se manda:
 * estado de la membresía, medidores de bolsa, próxima reserva. Lo demás es
 * accesorio y va después.
 */
class DashboardController extends Controller
{
    use ResuelveLaMembresia;

    public function __construct(private readonly ResumenDeBolsas $resumen)
    {
    }

    public function index(Request $request)
    {
        $usuario     = $request->user();
        $suscripcion = $this->membresiaVigente($usuario);

        $proxima = $usuario->reservas()
            ->with('espacio')
            ->confirmadas()
            ->where('fecha', '>=', today())
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get()
            // El filtro fino en PHP: una reserva de hoy que ya terminó no es la
            // «próxima», y comparar fecha+hora en SQL portable no compensa.
            ->first(fn (Reserva $reserva) => $reserva->finEnCalendario()->isFuture());

        return Inertia::render('Portal/Dashboard', [
            'estadoMembresia' => $this->estadoDeLaMembresia($usuario, $suscripcion),
            'suscripcion'     => $suscripcion ? [
                'id'             => $suscripcion->id,
                'plan'           => $suscripcion->plan?->only(['id', 'nombre', 'precio', 'periodo_label', 'color']),
                'fecha_inicio'   => $suscripcion->fecha_inicio->toDateString(),
                'fecha_fin'      => $suscripcion->fecha_fin->toDateString(),
                'dias_restantes' => $this->diasRestantes($suscripcion),
                'personas'       => $suscripcion->plan?->personas ?? 1,
                'companion'      => $suscripcion->companion?->only(['id', 'name']),
                'companion_face_id_ok' => (bool) $suscripcion->companion_face_id_ok,
                'ciclo_inicio'   => $suscripcion->cicloInicio()->toDateString(),
                'ciclo_fin'      => $suscripcion->cicloFin()->toDateString(),
            ] : null,

            'medidores' => $suscripcion ? $this->resumen->para($suscripcion) : [],

            'proximaReserva' => $proxima ? [
                'id'        => $proxima->id,
                'espacio'   => $proxima->espacio?->nombre,
                'tipo'      => $proxima->espacio?->tipo_label,
                'fecha'     => $proxima->fecha->toDateString(),
                'inicio'    => substr($proxima->hora_inicio, 0, 5),
                'fin'       => substr($proxima->hora_fin, 0, 5),
                'empieza_en' => $proxima->inicioEnCalendario()->toIso8601String(),
                'horas'     => $proxima->duracionEnHoras(),
            ] : null,

            'checkinActual' => $usuario->checkins()
                ->whereNull('hora_salida')
                ->latest('hora_entrada')
                ->first()
                ?->only(['id', 'hora_entrada']),

            'asesoriasPendientes' => $suscripcion
                ? $usuario->asesorias()
                    ->whereIn('estado', [EstadoAsesoria::Solicitada->value, EstadoAsesoria::Confirmada->value])
                    ->count()
                : 0,

            'avisos' => AnuncioCoworking::where('activo', true)
                ->where('fecha_inicio', '<=', today())
                ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', today()))
                ->orderByDesc('fecha_inicio')
                ->limit(3)
                ->get(['id', 'titulo', 'contenido', 'tipo', 'fecha_inicio']),

            'comunicados' => $usuario->comunicados()
                ->orderByDesc('created_at')
                ->limit(3)
                ->get(['id', 'titulo', 'mensaje', 'tipo', 'created_at']),

            'faceIdPendiente' => ! $usuario->face_id_ok,
        ]);
    }
}
