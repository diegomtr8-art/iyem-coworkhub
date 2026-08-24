<?php

namespace App\Http\Controllers;

use App\Models\Espacio;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EspaciosController extends Controller
{
    public function index()
    {
        return Inertia::render('Espacios/Index', [
            'espacios' => Espacio::withCount(['reservas', 'checkins'])->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Espacios/Form');
    }

    private function rules(): array
    {
        return [
            'nombre'      => 'required|string|max:100',
            'tipo'        => 'required|in:coworking,privado,sala_juntas,contenido,fotografia,escritorio,oficina_privada,cabina_telefonica,lounge',
            'capacidad'   => 'required|integer|min:1',
            'precio_hora' => 'nullable|numeric|min:0',
            'amenidades'  => 'nullable|array',
            'amenidades.*'=> 'string|max:100',
            'disponible'  => 'boolean',
            'piso'        => 'nullable|integer|min:1',
            'descripcion' => 'nullable|string|max:500',
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        Espacio::create($data);
        return redirect()->route('espacios.index')->with('success', 'Espacio creado exitosamente.');
    }

    public function edit(Espacio $espacio)
    {
        return Inertia::render('Espacios/Form', ['espacio' => $espacio]);
    }

    public function update(Request $request, Espacio $espacio)
    {
        $data = $request->validate($this->rules());
        $espacio->update($data);
        return redirect()->route('espacios.index')->with('success', 'Espacio actualizado exitosamente.');
    }

    public function destroy(Espacio $espacio)
    {
        $espacio->delete();
        return redirect()->route('espacios.index')->with('success', 'Espacio eliminado.');
    }
}
