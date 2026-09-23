<?php

namespace App\Servicios\Acceso;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Verifica el `id_token` que la app obtiene de Google.
 *
 * Google comprueba la firma y la caducidad en su endpoint `tokeninfo`; aquí se
 * comprueba lo que solo nosotros sabemos: que el token se emitió **para esta
 * app** (`aud` entre nuestros client IDs) y por Google (`iss`). Sin la
 * comprobación de `aud`, un token emitido para cualquier otra app del mundo
 * serviría para entrar a Nódico.
 */
class VerificadorDeGoogle
{
    private const EMISORES = ['accounts.google.com', 'https://accounts.google.com'];

    /** El perfil si el token es válido y para nosotros; `null` si no. */
    public function verificar(string $idToken): ?PerfilExterno
    {
        $clientes = (array) config('nodico.app_movil.google_client_ids', []);

        if ($clientes === []) {
            return null;
        }

        try {
            $respuesta = Http::timeout(8)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if (! $respuesta->ok()) {
            return null;
        }

        $datos = $respuesta->json();

        if (! in_array($datos['aud'] ?? null, $clientes, true)
            || ! in_array($datos['iss'] ?? null, self::EMISORES, true)
            || (int) ($datos['exp'] ?? 0) < time()
            || blank($datos['sub'] ?? null)
            || blank($datos['email'] ?? null)) {
            return null;
        }

        return new PerfilExterno(
            id: (string) $datos['sub'],
            correo: Str::lower(trim((string) $datos['email'])),
            nombre: $datos['name'] ?? null,
            avatar: $datos['picture'] ?? null,
            // tokeninfo devuelve el booleano como texto.
            correoVerificado: in_array($datos['email_verified'] ?? false, [true, 'true'], true),
        );
    }
}
