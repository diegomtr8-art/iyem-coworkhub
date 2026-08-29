<?php

namespace App\Http\Controllers;

use App\Enums\EventoAuth;
use App\Models\EventoAutenticacion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * B — Bitácora completa, para administración.
 *
 * Es la vista que responde a «¿alguien entró a esta cuenta?» y a «¿nos están
 * probando contraseñas?». Los intentos fallidos contra correos que no existen
 * también salen: son los que delatan un barrido.
 */
class BitacoraController extends Controller
{
    public function index(Request $request): Response
    {
        $filtroTipo   = $request->string('tipo')->toString();
        $filtroCorreo = $request->string('correo')->toString();
        $soloFallos   = $request->boolean('fallos');

        $eventos = EventoAutenticacion::query()
            ->with('usuario:id,name,email')
            ->when($filtroTipo !== '', fn ($q) => $q->where('tipo', $filtroTipo))
            ->when($filtroCorreo !== '', fn ($q) => $q->where('correo', 'like', '%' . $filtroCorreo . '%'))
            ->when($soloFallos, fn ($q) => $q->where('exito', false))
            ->recientes()
            ->paginate(50)
            ->withQueryString()
            ->through(fn (EventoAutenticacion $evento) => [
                'id'       => $evento->id,
                'etiqueta' => $evento->etiqueta,
                'tipo'     => $evento->tipo,
                'exito'    => $evento->exito,
                'delicado' => $evento->evento?->esDelicado() ?? false,
                'usuario'  => $evento->usuario?->only(['id', 'name', 'email']),
                'correo'   => $evento->correo,
                'ip'       => $evento->ip,
                'agente'   => $evento->agente,
                'contexto' => $evento->contexto,
                'cuando'   => $evento->created_at?->format('d/m/Y H:i:s'),
            ]);

        return Inertia::render('Bitacora/Index', [
            'eventos' => $eventos,
            'tipos'   => collect(EventoAuth::cases())
                ->map(fn (EventoAuth $tipo) => ['valor' => $tipo->value, 'etiqueta' => $tipo->etiqueta()])
                ->values(),
            'filtros' => [
                'tipo'   => $filtroTipo,
                'correo' => $filtroCorreo,
                'fallos' => $soloFallos,
            ],
        ]);
    }
}
