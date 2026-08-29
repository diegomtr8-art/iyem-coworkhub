<?php

namespace App\Http\Controllers;

use App\Models\Plane;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanesController extends Controller
{
    public function index()
    {
        return Inertia::render('Planes/Index', [
            'planes' => Plane::withCount('suscripciones')->orderBy('precio')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Planes/Form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'              => 'required|string|max:100',
            'subtitulo'           => 'nullable|string|max:200',
            'tipo'                => 'required|in:dia,semana,mes,anual,horas',
            'precio'              => 'required|numeric|min:0',
            'dias_cowork_mes'     => 'nullable|integer|min:1',
            'horas_sala_mes'      => 'nullable|numeric|min:0',
            'horas_contenido_mes' => 'nullable|numeric|min:0',
            'horas_asesoria_mes'  => 'nullable|numeric|min:0',
            'max_horas_sala_dia'  => 'nullable|numeric|min:0',
            'max_horas_contenido_dia' => 'nullable|numeric|min:0',
            'max_horas_asesoria_dia'  => 'nullable|numeric|min:0',
            'personas'            => 'required|integer|in:1,2',
            'horas_incluidas'     => 'nullable|integer|min:0',
            'max_reservas_mes'    => 'nullable|integer|min:0',
            'acceso_24h'          => 'boolean',
            'color'               => 'required|string',
            'destacado'           => 'boolean',
            'activo'              => 'boolean',
        ]);

        Plane::create($data);
        return redirect()->route('planes.index')->with('success', 'Plan creado exitosamente.');
    }

    public function edit(Plane $plane)
    {
        return Inertia::render('Planes/Form', ['plan' => $plane]);
    }

    public function update(Request $request, Plane $plane)
    {
        $data = $request->validate([
            'nombre'              => 'required|string|max:100',
            'subtitulo'           => 'nullable|string|max:200',
            'tipo'                => 'required|in:dia,semana,mes,anual,horas',
            'precio'              => 'required|numeric|min:0',
            'dias_cowork_mes'     => 'nullable|integer|min:1',
            'horas_sala_mes'      => 'nullable|numeric|min:0',
            'horas_contenido_mes' => 'nullable|numeric|min:0',
            'horas_asesoria_mes'  => 'nullable|numeric|min:0',
            'max_horas_sala_dia'  => 'nullable|numeric|min:0',
            'max_horas_contenido_dia' => 'nullable|numeric|min:0',
            'max_horas_asesoria_dia'  => 'nullable|numeric|min:0',
            'personas'            => 'required|integer|in:1,2',
            'horas_incluidas'     => 'nullable|integer|min:0',
            'max_reservas_mes'    => 'nullable|integer|min:0',
            'acceso_24h'          => 'boolean',
            'color'               => 'required|string',
            'destacado'           => 'boolean',
            'activo'              => 'boolean',
        ]);

        $plane->update($data);
        return redirect()->route('planes.index')->with('success', 'Plan actualizado exitosamente.');
    }

    public function destroy(Plane $plane)
    {
        $plane->delete();
        return redirect()->route('planes.index')->with('success', 'Plan eliminado.');
    }
}
