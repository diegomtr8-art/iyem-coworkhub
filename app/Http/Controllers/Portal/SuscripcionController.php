<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SuscripcionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $suscripcion = $user->suscripciones()
            ->with('plan')
            ->where('estatus', 'Activa')
            ->latest()
            ->first();

        $historial = $user->suscripciones()
            ->with('plan')
            ->where('estatus', '!=', 'Activa')
            ->orderByDesc('created_at')
            ->get();

        $horasUsadas = $user->checkins()
            ->whereMonth('hora_entrada', now()->month)
            ->whereYear('hora_entrada', now()->year)
            ->sum('duracion_minutos') / 60;

        return Inertia::render('Portal/MiSuscripcion', [
            'suscripcion' => $suscripcion,
            'historial'   => $historial,
            'horasUsadas' => round($horasUsadas, 1),
        ]);
    }
}
