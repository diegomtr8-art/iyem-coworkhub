<?php

namespace App\Http\Controllers;

use App\Models\AnuncioCoworking;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnunciosController extends Controller
{
    public function index()
    {
        return Inertia::render('Anuncios/Index', [
            'anuncios' => AnuncioCoworking::orderByDesc('fecha_inicio')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'titulo'       => 'required|string|max:150',
            'contenido'    => 'required|string',
            'tipo'         => 'required|in:general,oferta,evento,mantenimiento',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'nullable|date|after_or_equal:fecha_inicio',
            'activo'       => 'boolean',
        ]);

        AnuncioCoworking::create($data);
        return back()->with('success', 'Anuncio creado exitosamente.');
    }

    public function update(Request $request, AnuncioCoworking $anuncio)
    {
        $data = $request->validate([
            'titulo'       => 'required|string|max:150',
            'contenido'    => 'required|string',
            'tipo'         => 'required|in:general,oferta,evento,mantenimiento',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'nullable|date|after_or_equal:fecha_inicio',
            'activo'       => 'boolean',
        ]);

        $anuncio->update($data);
        return back()->with('success', 'Anuncio actualizado.');
    }

    public function destroy(AnuncioCoworking $anuncio)
    {
        $anuncio->delete();
        return back()->with('success', 'Anuncio eliminado.');
    }
}
