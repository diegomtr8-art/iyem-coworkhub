<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AnuncioCoworking;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load([
            'suscripciones.plan',
            'checkins',
        ]);

        $suscripcionActiva = $user->suscripciones
            ->where('estatus', 'Activa')
            ->sortByDesc('fecha_inicio')
            ->first();

        $checkinActual = $user->checkins->whereNull('hora_salida')->first();

        $proximasReservas = $user->reservas()
            ->with('espacio')
            ->where('fecha', '>=', today())
            ->where('estatus', 'Confirmada')
            ->orderBy('fecha')->orderBy('hora_inicio')
            ->limit(3)
            ->get();

        $comunicados = $user->comunicados()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $anuncios = AnuncioCoworking::where('activo', true)
            ->where('fecha_inicio', '<=', today())
            ->where(fn($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', today()))
            ->orderByDesc('fecha_inicio')
            ->limit(3)
            ->get();

        // Calcular horas y días restantes
        $resumen = null;
        if ($suscripcionActiva) {
            $plan = $suscripcionActiva->plan;
            $resumen = [
                'horas_sala_max'          => $plan?->horas_sala_mes,
                'horas_sala_usadas'       => $suscripcionActiva->horas_sala_usadas ?? 0,
                'horas_sala_restantes'    => $suscripcionActiva->horasSalaRestantes(),
                'horas_contenido_max'     => $plan?->horas_contenido_mes,
                'horas_contenido_usadas'  => $suscripcionActiva->horas_contenido_usadas ?? 0,
                'horas_contenido_restantes'=> $suscripcionActiva->horasContenidoRestantes(),
                'dias_cowork_max'         => $plan?->dias_cowork_mes,
                'dias_usados'             => $suscripcionActiva->dias_usados ?? 0,
                'dias_restantes_mes'      => $suscripcionActiva->diasRestantesMes(),
                'max_horas_sala_dia'      => $plan?->max_horas_sala_dia,
                'personas'                => $plan?->personas ?? 1,
                'companion'               => $suscripcionActiva->companion,
                'companion_face_id_ok'    => $suscripcionActiva->companion_face_id_ok,
            ];
        }

        return Inertia::render('Portal/Dashboard', [
            'suscripcion'      => $suscripcionActiva,
            'checkinActual'    => $checkinActual,
            'proximasReservas' => $proximasReservas,
            'comunicados'      => $comunicados,
            'anuncios'         => $anuncios,
            'resumen'          => $resumen,
        ]);
    }
}
