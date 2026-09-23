<?php

namespace App\Servicios\Acceso;

/**
 * Lo que un proveedor externo (Google) afirma de una persona, ya normalizado.
 *
 * Existe para que el flujo web (Socialite) y el de la app (id_token verificado)
 * entreguen exactamente lo mismo a `IdentidadDeProveedor`.
 */
final class PerfilExterno
{
    public function __construct(
        public readonly string $id,
        public readonly string $correo,
        public readonly ?string $nombre,
        public readonly ?string $avatar,
        public readonly bool $correoVerificado,
    ) {
    }
}
