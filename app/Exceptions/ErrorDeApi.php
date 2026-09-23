<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Error de la API móvil con un `codigo` estable (docs/API-MOVIL.md §4.3).
 *
 * El `codigo` es para la app —decide qué pantalla enseñar— y el mensaje para la
 * persona, ya en español y listo para mostrar. `extra` lleva lo que esa
 * pantalla necesita (el motivo de la suspensión, los documentos pendientes…).
 */
class ErrorDeApi extends RuntimeException
{
    public function __construct(
        public readonly int $estado,
        public readonly string $codigo,
        string $mensaje,
        public readonly array $extra = [],
    ) {
        parent::__construct($mensaje);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'codigo'  => $this->codigo,
            ...$this->extra,
        ], $this->estado);
    }
}
