<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\Espacio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckinAdminController extends Controller
{
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
            'miembros'  => User::where('tipo', 'miembro')->get(['id', 'name', 'empresa']),
        ]);
    }

    public function entrada(Request $request)
    {
        $data = $request->validate([
            'user_id'    => 'required|exists:users,id',
            'espacio_id' => 'required|exists:espacios,id',
        ]);

        Checkin::where('user_id', $data['user_id'])->whereNull('hora_salida')->each(function ($c) {
            $minutos = (int) Carbon::parse($c->hora_entrada)->diffInMinutes(now());
            $c->update(['hora_salida' => now(), 'duracion_minutos' => $minutos]);
        });

        Checkin::create([...$data, 'hora_entrada' => now()]);
        return back()->with('success', 'Check-in registrado.');
    }

    public function salida(Checkin $checkin)
    {
        $minutos = (int) Carbon::parse($checkin->hora_entrada)->diffInMinutes(now());
        $checkin->update(['hora_salida' => now(), 'duracion_minutos' => $minutos]);
        return back()->with('success', 'Check-out registrado. Duración: ' . $minutos . ' minutos.');
    }
}
