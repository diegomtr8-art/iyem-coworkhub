<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\Espacio;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    public function entrada(Request $request)
    {
        $user        = $request->user();
        $suscripcion = $user->suscripciones()->where('estatus', 'Activa')->latest()->first();

        if (!$suscripcion) {
            return back()->withErrors(['general' => 'Necesitas una membresía activa para hacer check-in.']);
        }

        // Cerrar checkins abiertos si los hubiera
        $user->checkins()->whereNull('hora_salida')->each(function ($c) {
            $mins = (int) Carbon::parse($c->hora_entrada)->diffInMinutes(now());
            $c->update(['hora_salida' => now(), 'duracion_minutos' => $mins]);
        });

        // Usar el espacio del request o el primer espacio coworking disponible
        $espacio = null;
        if ($request->filled('espacio_id')) {
            $espacio = Espacio::find($request->espacio_id);
        }
        if (!$espacio) {
            $espacio = Espacio::where('tipo', 'coworking')->where('disponible', true)->first();
        }

        Checkin::create([
            'user_id'      => $user->id,
            'espacio_id'   => $espacio?->id,
            'hora_entrada' => now(),
        ]);

        return back()->with('success', '¡Check-in registrado! Bienvenido a NODICO.');
    }

    public function salida(Request $request)
    {
        $checkin = $request->user()->checkins()->whereNull('hora_salida')->latest()->first();

        if (!$checkin) {
            return back()->with('error', 'No tienes un check-in activo.');
        }

        $mins = (int) Carbon::parse($checkin->hora_entrada)->diffInMinutes(now());
        $checkin->update(['hora_salida' => now(), 'duracion_minutos' => $mins]);

        $horas = intdiv($mins, 60);
        $minutos = $mins % 60;
        $tiempo = $horas > 0 ? "{$horas}h {$minutos}min" : "{$mins} min";

        return back()->with('success', "Check-out registrado. Estuviste {$tiempo} en NODICO. ¡Hasta pronto!");
    }
}
