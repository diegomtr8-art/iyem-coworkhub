<?php

namespace App\Http\Controllers;

use App\Models\DirectorioEmprendedor;
use App\Servicios\Emprendedores\RotacionDeEmprendedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fase 4.H — catálogo de emprendimientos. Una sola tabla alimenta el directorio
 * de `/actividades` y el «emprendedor de la semana» rotativo.
 */
class EmprendedoresController extends Controller
{
    public function __construct(private readonly RotacionDeEmprendedor $rotacion)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Emprendedores/Index', [
            'emprendedores' => DirectorioEmprendedor::orderByDesc('activo')->orderBy('orden')->orderBy('nombre')->get()
                ->map(fn (DirectorioEmprendedor $e) => [
                    'id'             => $e->id,
                    'nombre'         => $e->nombre,
                    'foto'           => $e->foto,
                    'descripcion'    => $e->descripcion,
                    'giro'           => $e->giro,
                    'municipio'      => $e->municipio,
                    'instagram'      => $e->instagram,
                    'sitio_web'      => $e->sitio_web,
                    'url_destino'    => $e->url_destino,
                    'egresado_iyem'  => $e->egresado_iyem,
                    'activo'         => $e->activo,
                    'orden'          => $e->orden,
                    'elegible'       => $e->elegible_destacado,
                    'destacado'      => $e->destacado_semana,
                    'ultima_vez'     => $e->destacado_ultima_vez?->toDateString(),
                    'enlace'         => $e->enlace, // el «Saber más» (H.4)
                ]),

            // Calendario de rotación (H.3): quién esta semana y quién sigue.
            'calendario' => $this->rotacion->calendario(6),
        ]);
    }

    public function guardar(Request $request, ?DirectorioEmprendedor $emprendedor = null): RedirectResponse
    {
        $datos = $request->validate([
            'nombre'         => ['required', 'string', 'max:150'],
            'foto'           => ['nullable', 'string', 'max:255'],
            'descripcion'    => ['nullable', 'string', 'max:1000'],
            'giro'           => ['nullable', 'string', 'max:120'],
            'municipio'      => ['nullable', 'string', 'max:120'],
            'instagram'      => ['nullable', 'string', 'max:120'],
            'sitio_web'      => ['nullable', 'string', 'max:255'],
            'url_destino'    => ['nullable', 'string', 'max:255'],
            'egresado_iyem'  => ['boolean'],
            'activo'         => ['boolean'],
            'orden'          => ['nullable', 'integer', 'min:0'],
            'elegible_destacado' => ['boolean'],
        ]);

        $emprendedor?->exists ? $emprendedor->update($datos) : DirectorioEmprendedor::create($datos);

        return back()->with('success', $emprendedor?->exists ? 'Emprendedor actualizado.' : 'Emprendedor agregado.');
    }

    /** H.3 — fijar uno como destacado esta semana, sin romper el ciclo. */
    public function fijar(DirectorioEmprendedor $emprendedor): RedirectResponse
    {
        $this->rotacion->fijar($emprendedor);

        return back()->with('success', "{$emprendedor->nombre} queda destacado esta semana.");
    }
}
