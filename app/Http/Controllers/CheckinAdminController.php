<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\Espacio;
use App\Models\User;
use App\Servicios\Accesos\RegistroDeAcceso;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Check-in y check-out desde el mostrador.
 *
 * Comparte `RegistroDeAcceso` con el portal del miembro: la regla de que un día
 * se consume una sola vez por día natural (BUG-05) tiene que valer igual entre
 * a quien entre por su cuenta o lo registre recepción.
 */
class CheckinAdminController extends Controller
{
    public function __construct(private readonly RegistroDeAcceso $accesos)
    {
    }

    public function index()
    {
        $activos = Checkin::with(['user', 'espacio'])
            ->whereNull('hora_salida')
            ->latest('hora_entrada')
            ->get();

        $historial = Checkin::with(['user', 'espacio'])
            ->whereNotNull('hora_salida')
            ->whereDate('hora_entrada', today())
            ->latest('hora_entrada')
            ->get();

        return Inertia::render('Checkins/Index', [
            'activos'   => $activos,
            'historial' => $historial,
            'espacios'  => Espacio::where('disponible', true)->get(['id', 'nombre', 'tipo']),
            'miembros'  => User::miembros()->get(['id', 'name', 'empresa']),
        ]);
    }

    public function entrada(Request $request)
    {
        $datos = $request->validate([
            'user_id'    => ['required', 'exists:users,id'],
            'espacio_id' => ['required', 'exists:espacios,id'],
        ]);

        $this->accesos->entrada(
            User::findOrFail($datos['user_id']),
            Espacio::find($datos['espacio_id']),
        );

        return back()->with('success', 'Check-in registrado.');
    }

    public function salida(Checkin $checkin)
    {
        $acceso = $this->accesos->salida($checkin->user);

        return back()->with('success', $acceso
            ? 'Check-out registrado. Duración: ' . $acceso->duracion_minutos . ' minutos.'
            : 'Ese miembro ya no tenía un acceso abierto.');
    }
}
