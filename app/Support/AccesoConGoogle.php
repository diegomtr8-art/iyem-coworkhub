<?php

namespace App\Support;

/**
 * Si el ingreso con Google está disponible: el interruptor
 * `NODICO_GOOGLE_LOGIN_ENABLED` **y** las tres credenciales de Google Cloud.
 *
 * Lo consultan el botón (HandleInertiaRequests) y la ruta (OAuthController),
 * para que nunca se vea un botón que truene: encender el interruptor sin
 * credenciales deja todo apagado. Qué poner y cómo pedirlo:
 * docs/AUTH-PROVEEDORES.md.
 */
class AccesoConGoogle
{
    public static function disponible(): bool
    {
        return (bool) config('nodico.acceso.google')
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}
