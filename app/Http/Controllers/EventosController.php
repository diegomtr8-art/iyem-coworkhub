<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventosController extends Controller
{
    public function index()
    {
        return Inertia::render('Eventos/Index', [
            'proximos' => Evento::activos()->proximos()->get(),
            'pasados'  => Evento::pasados()->limit(10)->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Eventos/Form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'titulo'        => 'required|string|max:200',
            'descripcion'   => 'nullable|string',
            'fecha'         => 'required|date',
            'hora_inicio'   => 'required|date_format:H:i',
            'hora_fin'      => 'nullable|date_format:H:i|after:hora_inicio',
            'lugar'         => 'nullable|string|max:200',
            'cupo_maximo'   => 'nullable|integer|min:1',
            'precio'        => 'nullable|numeric|min:0',
            'solo_miembros' => 'boolean',
            'activo'        => 'boolean',
        ]);

        Evento::create($data);
        return redirect()->route('eventos.admin.index')->with('success', 'Evento creado exitosamente.');
    }

    public function edit(Evento $evento)
    {
        return Inertia::render('Eventos/Form', ['evento' => $evento]);
    }

    public function update(Request $request, Evento $evento)
    {
        $data = $request->validate([
            'titulo'        => 'required|string|max:200',
            'descripcion'   => 'nullable|string',
            'fecha'         => 'required|date',
            'hora_inicio'   => 'required|date_format:H:i',
            'hora_fin'      => 'nullable|date_format:H:i|after:hora_inicio',
            'lugar'         => 'nullable|string|max:200',
            'cupo_maximo'   => 'nullable|integer|min:1',
            'precio'        => 'nullable|numeric|min:0',
            'solo_miembros' => 'boolean',
            'activo'        => 'boolean',
        ]);

        $evento->update($data);
        return redirect()->route('eventos.admin.index')->with('success', 'Evento actualizado exitosamente.');
    }

    public function destroy(Evento $evento)
    {
        $evento->delete();
        return redirect()->route('eventos.admin.index')->with('success', 'Evento eliminado.');
    }
}
