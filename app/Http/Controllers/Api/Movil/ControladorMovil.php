<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Base de los controladores de la API móvil.
 *
 * Todo éxito sale como `{ "data": … }` (docs/API-MOVIL.md §4.2), y las listas
 * paginadas por cursor añaden `meta.siguiente`.
 */
abstract class ControladorMovil extends Controller
{
    protected function datos(mixed $datos, int $estado = 200, array $extra = []): JsonResponse
    {
        return response()->json(['data' => $datos, ...$extra], $estado);
    }

    /**
     * @param  \Illuminate\Contracts\Pagination\CursorPaginator  $pagina
     */
    protected function pagina($pagina, callable $mapa): JsonResponse
    {
        return $this->datos(
            collect($pagina->items())->map($mapa)->values(),
            extra: ['meta' => ['siguiente' => $pagina->nextCursor()?->encode()]],
        );
    }
}
