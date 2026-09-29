<?php

/*
|--------------------------------------------------------------------------
| Pasarela del cobro con tarjeta
|--------------------------------------------------------------------------
|
| Nódico cobra con tarjeta por **una** pasarela a la vez, elegida aquí. Las dos
| conviven en el código mientras dura la migración de Stripe a BBVA: si BBVA
| se atora, se vuelve a Stripe cambiando una línea del .env, sin revertir el
| repositorio (docs/PAGOS-BBVA.md).
|
| Lo que ya se cobró sigue su curso aunque se cambie: el webhook de Stripe
| sigue escuchando, y los cargos de BBVA pendientes los sigue confirmando
| `nodico:confirmar-cargos`.
|
*/

return [

    // stripe | bbva
    'pasarela' => env('PAGOS_PASARELA', 'stripe'),

    'bbva' => [
        'merchant_id'  => env('BBVA_MERCHANT_ID'),
        'llave_privada' => env('BBVA_LLAVE_PRIVADA'),
        // Número de afiliación: la documentación lo pide en cada cargo
        // (`affiliation_bbva`, requerido). Lo da el ejecutivo de cuenta.
        'afiliacion'   => env('BBVA_AFILIACION'),
        'sandbox'      => (bool) env('BBVA_SANDBOX', true),

        // https://docs.ecommercebbva.com/#api-endpoints
        'url_sandbox'    => 'https://sand-api.ecommercebbva.com',
        'url_produccion' => 'https://api.ecommercebbva.com',

        'timeout' => 30,

        // Un cargo que nadie terminó (se abandonó el 3-D Secure) se sigue
        // consultando este tiempo; después se da por abandonado. La
        // documentación no dice cuánto vive un `charge_pending` en BBVA.
        'horas_de_espera' => 24,

        // Mientras un cargo del mismo plan siga pendiente y sea así de
        // reciente, se retoma en vez de crear otro: es lo que evita cobrar dos
        // veces si se corta la red o se pulsa dos veces.
        'minutos_para_retomar' => 30,
    ],

];
