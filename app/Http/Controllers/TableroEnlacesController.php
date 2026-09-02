<?php

namespace App\Http\Controllers;

use App\Models\TableroEnlace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestión de los enlaces públicos del tablero (Fase 6). Solo administración.
 */
class TableroEnlacesController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Tablero/Enlaces', [
            'enlaces' => TableroEnlace::orderByDesc('id')->get()->map(fn (TableroEnlace $e) => [
                'id'            => $e->id,
                'nombre'        => $e->nombre,
                'url'           => route('tablero.publico', $e->token),
                'ultimo_uso_en' => $e->ultimo_uso_en?->toIso8601String(),
                'revocado'      => $e->revocado_en !== null,
                'creado'        => $e->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function generar(Request $request): RedirectResponse
    {
        $datos = $request->validate(['nombre' => ['required', 'string', 'max:80']]);

        TableroEnlace::generar($datos['nombre'], $request->user()->id);

        return back()->with('success', 'Enlace del tablero generado.');
    }

    public function revocar(Request $request, TableroEnlace $enlace): RedirectResponse
    {
        $enlace->forceFill(['revocado_en' => now()])->save();

        return back()->with('success', 'Enlace revocado. Deja de funcionar de inmediato.');
    }
}
