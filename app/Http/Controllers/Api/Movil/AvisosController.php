<?php

namespace App\Http\Controllers\Api\Movil;

use App\Models\AnuncioCoworking;
use App\Models\Comunicado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Avisos (docs/API-MOVIL.md §6.8): los del coworking, para todos, y los
 * comunicados personales de cada miembro.
 */
class AvisosController extends ControladorMovil
{
    public function index(Request $request): JsonResponse
    {
        return $this->datos([
            'anuncios' => AnuncioCoworking::where('activo', true)
                ->where('fecha_inicio', '<=', \App\Models\Reserva::hoyYmd())
                ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', \App\Models\Reserva::hoyYmd()))
                ->orderByDesc('fecha_inicio')
                ->limit(20)
                ->get()
                ->map(fn (AnuncioCoworking $a) => [
                    'id'        => $a->id,
                    'titulo'    => $a->titulo,
                    'contenido' => $a->contenido,
                    'tipo'      => $a->tipo,
                    'desde'     => $a->fecha_inicio?->toDateString(),
                    'hasta'     => $a->fecha_fin?->toDateString(),
                ])
                ->values(),

            'comunicados' => $request->user()->comunicados()
                ->orderByDesc('created_at')
                ->limit(30)
                ->get()
                ->map(fn (Comunicado $c) => [
                    'id'      => $c->id,
                    'titulo'  => $c->titulo,
                    'mensaje' => $c->mensaje,
                    'tipo'    => $c->tipo,
                    'leido'   => (bool) $c->leido,
                    'fecha'   => $c->created_at?->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    public function leido(Request $request, int $id): JsonResponse
    {
        $comunicado = $request->user()->comunicados()->whereKey($id)->first();

        abort_unless($comunicado, 404);

        $comunicado->update(['leido' => true]);

        return $this->datos(['leido' => true]);
    }
}
