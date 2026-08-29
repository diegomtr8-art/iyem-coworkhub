<?php

namespace App\Http\Controllers;

use App\Models\Espacio;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReservasController extends Controller
{
    public function index(Request $request)
    {
        $reservas = Reserva::with(['user', 'espacio'])
            ->when($request->fecha, fn($q, $f) => $q->whereDate('fecha', $f))
            ->when($request->espacio_id, fn($q, $e) => $q->where('espacio_id', $e))
            ->when($request->estatus, fn($q, $s) => $q->where('estatus', $s))
            ->when($request->user_id, fn($q, $u) => $q->where('user_id', $u))
            ->orderByDesc('fecha')
            ->paginate(15)->withQueryString();

        return Inertia::render('Reservas/Index', [
            'reservas'  => $reservas,
            'espacios'  => Espacio::orderBy('nombre')->get(['id', 'nombre', 'tipo']),
            'miembros'  => User::miembros()->orderBy('name')->get(['id', 'name']),
            'filters'   => $request->only(['fecha', 'espacio_id', 'estatus', 'user_id']),
        ]);
    }

    public function update(Request $request, Reserva $reserva)
    {
        $request->validate(['estatus' => 'required|in:Confirmada,Cancelada,Completada,No_Show']);
        $reserva->update(['estatus' => $request->estatus]);
        return back()->with('success', 'Reserva actualizada.');
    }

    public function destroy(Reserva $reserva)
    {
        $reserva->delete();
        return back()->with('success', 'Reserva eliminada.');
    }
}
