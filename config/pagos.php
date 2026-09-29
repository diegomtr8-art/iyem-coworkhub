<?php

/*
|--------------------------------------------------------------------------
| Pasarela del cobro con tarjeta
|--------------------------------------------------------------------------
|
| Nódico cobra con tarjeta por **una** pasarela a la vez, elegida aquí:
|
|  - `stripe`:  Stripe con Laravel Cashier (lo de siempre).
|  - `openpay`: Openpay, la pasarela de BBVA con la API completa (clientes,
|               tarjetas guardadas, planes, suscripciones, webhooks).
|  - `bbva`:    Ecommerce BBVA. La MISMA plataforma que Openpay, pero con marca
|               propia y una API recortada (solo cargos; ver
|               docs/PAGOS-BBVA.md).
|
| Openpay y Ecommerce BBVA comparten el código (`PasarelaOpenpay`): cambian las
| direcciones, las llaves y lo que cada una permite. Volver a Stripe es cambiar
| una línea del .env, sin revertir el repositorio.
|
| Lo ya cobrado sigue su curso aunque se cambie: el webhook de Stripe sigue
| escuchando, y los cargos pendientes de cada plataforma los sigue
| confirmando `nodico:confirmar-cargos` con las llaves de SU plataforma.
|
*/

$plataforma = fn (string $prefijo, array $extra) => [
    'merchant_id'   => env("{$prefijo}_MERCHANT_ID"),
    'llave_privada' => env("{$prefijo}_LLAVE_PRIVADA"),
    // La única que puede llegar al navegador: solo sirve para crear tokens de
    // tarjeta con openpay.js.
    'llave_publica' => env("{$prefijo}_LLAVE_PUBLICA"),
    'sandbox'       => (bool) env("{$prefijo}_SANDBOX", true),
    'timeout'       => 30,
    ...$extra,
];

return [

    // stripe | openpay | bbva
    'pasarela' => env('PAGOS_PASARELA', 'stripe'),

    // Un cargo que nadie terminó (se abandonó el 3-D Secure) se sigue
    // consultando este tiempo; después se da por abandonado. Ninguna de las
    // dos documentaciones dice cuánto vive un `charge_pending`.
    'horas_de_espera' => 24,

    // Mientras un cargo del mismo plan siga pendiente y sea así de reciente,
    // se retoma en vez de crear otro: evita cobrar dos veces si se corta la red
    // o se pulsa dos veces.
    'minutos_para_retomar' => 30,

    'openpay' => $plataforma('OPENPAY', [
        // https://documents.openpay.mx/docs/api/index.html#api-endpoints
        'url_sandbox'    => 'https://sandbox-api.openpay.mx',
        'url_produccion' => 'https://api.openpay.mx',
        // Openpay no usa número de afiliación.
        'afiliacion'     => null,
        // Openpay exige para producción su librería web (la tarjeta se teclea
        // en Nódico y nunca pasa por el servidor) y su antifraude.
        'captura'        => 'token',
        // `si_hace_falta`: el cargo va sin 3-D Secure y, si el antifraude lo
        // rechaza por riesgo (3005), se reintenta con 3-D Secure, como indica
        // https://documents.openpay.mx/docs/three-d-secure. `siempre`: todos
        // los cargos pasan por la autenticación del banco.
        'tres_d_secure'  => env('OPENPAY_3DS', 'si_hace_falta'),
        // Planes recurrentes: reintentos del cobro mensual y cómo queda la
        // suscripción al agotarlos (decisión de Diego, 29-sep-2026).
        'reintentos'         => 2,
        'estado_tras_reintentos' => 'unpaid',
    ]),

    'bbva' => $plataforma('BBVA', [
        // https://docs.ecommercebbva.com/#api-endpoints
        'url_sandbox'    => 'https://sand-api.ecommercebbva.com',
        'url_produccion' => 'https://api.ecommercebbva.com',
        // `affiliation_bbva`, requerido en cada cargo. Lo da el ejecutivo.
        'afiliacion'     => env('BBVA_AFILIACION'),
        // `token`: tarjeta tecleada en Nódico («cargo sin VPOS», requiere
        // autorización del ejecutivo). `vpos`: formulario del banco.
        'captura'        => env('BBVA_CAPTURA', 'vpos'),
        // BBVA aplica 3-D Secure por defecto en el servidor.
        'tres_d_secure'  => 'del_banco',
    ]),

];
