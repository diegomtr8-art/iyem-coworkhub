<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Correo del formulario "Hablemos"
    |--------------------------------------------------------------------------
    */
    'contacto_email' => env('NODICO_CONTACTO_EMAIL', 'contacto@nodico.com.mx'),

    /*
    |--------------------------------------------------------------------------
    | Calendario de talleres (Luma)
    |--------------------------------------------------------------------------
    */
    'luma_embed' => env('NODICO_LUMA_EMBED', 'https://luma.com/embed/calendar/cal-ZE3dbDW6bLs4v7j/events?lt=dark'),

    /*
    |--------------------------------------------------------------------------
    | Datos de contacto públicos
    |--------------------------------------------------------------------------
    | Tomados de la ficha de Google Maps del lugar (2026-08-28).
    */
    'telefono'          => env('NODICO_TELEFONO', '999 461 5676'),
    'telefono_e164'     => env('NODICO_TELEFONO_E164', '+529994615676'),
    'direccion'         => env('NODICO_DIRECCION', 'Avenida Principal, Industrias No Contaminantes 13613, Hacienda Sodzil Nte., 97110 Mérida, Yuc.'),
    'direccion_corta'   => env('NODICO_DIRECCION_CORTA', 'Hacienda Sodzil Nte., Mérida, Yucatán'),
    'maps_url'          => env('NODICO_MAPS_URL', 'https://maps.app.goo.gl/zRrEqEoMLohqnwEx5'),
    // Embed sin clave de API, con las coordenadas de la ficha del lugar.
    'maps_embed'        => env('NODICO_MAPS_EMBED', 'https://maps.google.com/maps?q=21.0527159,-89.6413298&hl=es&z=16&output=embed'),
    'horarios'          => env('NODICO_HORARIOS', 'Lunes a viernes, 9:00 a 19:00 h'),
    'horarios_detalle'  => 'Sábados y domingos cerrado',

    /*
    |--------------------------------------------------------------------------
    | Redes sociales
    |--------------------------------------------------------------------------
    */
    'redes' => [
        'instagram' => 'https://www.instagram.com/nodicomx',
        'facebook'  => 'https://www.facebook.com/nodicomx',
        'linkedin'  => 'https://www.linkedin.com/in/nodico-club-de-emprendedores-316513377/',
    ],

    'instagram_handle' => 'nodicomx',

    /*
    |--------------------------------------------------------------------------
    | Video institucional
    |--------------------------------------------------------------------------
    */
    'video_id' => env('NODICO_VIDEO_ID', 'Ml4sprGUqzc'),

    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    | `host_canonico` fija de una vez si el sitio vive con o sin www. Mientras
    | esté en null, la canónica usa el host de la petición tal cual.
    | PENDIENTE: Diego debe decidir para producción.
    */
    'host_canonico' => env('NODICO_HOST_CANONICO'),
    'sufijo_titulo' => 'Nódico',
];
