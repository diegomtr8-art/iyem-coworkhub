<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Espacio;
use App\Servicios\Accesos\RegistroDeAcceso;
use Illuminate\Http\Request;

/**
 * Entrada y salida del miembro desde su portal.
 *
 * Toda la regla vive en `RegistroDeAcceso`: aquí solo se traduce a mensajes.
 * Antes esta clase y `CheckinAdminController` tenían la misma lógica copiada, y
 * ninguna de las dos consumía días (BUG-05).
 */
class CheckinController extends Controller
{
    public function __construct(private readonly RegistroDeAcceso $accesos)
    {
    }

    public function entrada(Request $request)
    {
        $espacio = $request->filled('espacio_id')
            ? Espacio::find($request->integer('espacio_id'))
            : null;

        $this->accesos->entrada($request->user(), $espacio);

        return back()->with('success', '¡Check-in registrado! Bienvenido a Nódico.');
    }

    public function salida(Request $request)
    {
        $acceso = $this->accesos->salida($request->user());

        if (! $acceso) {
            return back()->with('error', 'No tienes un check-in activo.');
        }

        return back()->with('success', 'Check-out registrado. Estuviste '
            . $this->enPalabras($acceso->duracion_minutos) . ' en Nódico. ¡Hasta pronto!');
    }

    private function enPalabras(int $minutos): string
    {
        $horas   = intdiv($minutos, 60);
        $resto   = $minutos % 60;

        return $horas > 0 ? "{$horas} h {$resto} min" : "{$minutos} min";
    }
}
