<?php

namespace App\Servicios\Pagos\Bbva;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Una respuesta de error de la API de BBVA, con su objeto de error
 * (https://docs.ecommercebbva.com/#objeto-error): `category`, `error_code`,
 * `description`, `http_code`, `request_id`.
 *
 * El mensaje de la excepción es técnico (para la bitácora). Lo que se le dice
 * a la persona sale de `MensajesDeBbva`.
 */
class ErrorDeBbva extends RuntimeException
{
    public function __construct(
        public readonly ?int $codigo,
        public readonly ?string $descripcion,
        public readonly ?int $http,
        public readonly ?string $categoria = null,
        public readonly ?string $requestId = null,
    ) {
        parent::__construct(sprintf(
            'BBVA respondió %s (código %s): %s',
            $http ?? '¿?',
            $codigo ?? '¿?',
            $descripcion ?? 'sin descripción',
        ));
    }

    public static function desde(Response $respuesta): self
    {
        $cuerpo = $respuesta->json() ?? [];

        return new self(
            isset($cuerpo['error_code']) ? (int) $cuerpo['error_code'] : null,
            $cuerpo['description'] ?? $respuesta->body(),
            $respuesta->status(),
            $cuerpo['category'] ?? null,
            $cuerpo['request_id'] ?? null,
        );
    }

    public static function sinConexion(string $detalle): self
    {
        return new self(null, "Sin conexión con BBVA: {$detalle}", null);
    }

    /** Para la bitácora: nunca incluye llaves. */
    public function contexto(): array
    {
        return [
            'codigo'     => $this->codigo,
            'http'       => $this->http,
            'categoria'  => $this->categoria,
            'request_id' => $this->requestId,
            'detalle'    => $this->descripcion,
        ];
    }
}
