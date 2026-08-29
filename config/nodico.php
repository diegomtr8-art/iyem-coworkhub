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
    | `host_canonico` fija el origen de la canónica. Diego decidió el 29 de
    | agosto de 2026: **el sitio vive sin www**, o sea `https://nodico.com.mx`.
    |
    | Se deja por entorno en vez de fijo, porque staging tiene su propio host.
    | Si no se define, se usa `app.url`, que ya apunta al host correcto en cada
    | entorno; nunca el host de la petición, que es manipulable.
    */
    'host_canonico' => env('NODICO_HOST_CANONICO'),
    'sufijo_titulo' => 'Nódico',

    /*
    |--------------------------------------------------------------------------
    | Metadatos por página
    |--------------------------------------------------------------------------
    | Vivían dentro de cada componente Vue, y por tanto sólo existían después de
    | que el navegador ejecutara el JavaScript. WhatsApp, Slack, Telegram,
    | LinkedIn y Twitter/X no lo ejecutan: al compartir un enlace no veían ni
    | `og:title` ni `og:image`, así que no salía tarjeta.
    |
    | Ahora la copia vive aquí, el middleware la comparte y `app.blade.php` la
    | emite en el HTML. `Meta.vue` sigue encargándose de la navegación dentro
    | de la SPA, leyendo de la misma fuente para que no puedan divergir.
    |
    | La clave es el nombre de la ruta. `imagen` es el archivo de /img/og/ sin
    | extensión.
    */
    'seo_paginas' => [
        'home' => [
            'titulo'      => 'Coworking en Mérida para emprendedores',
            'descripcion' => 'Nódico es el coworking del Instituto Yucateco de Emprendedores en Mérida: espacio colaborativo, sala de creación de contenido, salones para eventos y una comunidad que impulsa tu proyecto.',
            'imagen'      => 'home',
        ],
        'nosotros' => [
            'titulo'      => 'Nosotros',
            'descripcion' => 'Más que un espacio físico, Nódico es una comunidad profesional en Mérida donde la colaboración, la vinculación estratégica y la formación continua convierten ideas en proyectos de impacto.',
            'imagen'      => 'nosotros',
        ],
        'membresias' => [
            'titulo'      => 'Membresías y precios',
            'descripcion' => 'Day-Pass, Nódico Flex, Nodo Pro y Nodo Match: elige la membresía de coworking que se ajusta a tu proyecto. Desde $79 MXN, con sala de creación de contenido, café y comunidad incluidos.',
            'imagen'      => 'membresias',
        ],
        'eventos' => [
            'titulo'      => 'Salones para eventos',
            'descripcion' => 'Renta los salones Yucatán Emprende de Nódico en Mérida: 15x14 m, hasta 120 personas, proyector, sonido, internet y mobiliario incluido desde $600 MXN por hora.',
            'imagen'      => 'eventos',
        ],
        'actividades' => [
            'titulo'      => 'Comunidad y actividades',
            'descripcion' => 'Talleres, eventos y el directorio de emprendedores de Nódico. Conoce las actividades del mes y a la comunidad que forma parte de los programas de incubación del IYEM.',
            'imagen'      => 'actividades',
        ],
        'privacidad' => [
            'titulo'      => 'Aviso de privacidad',
            'descripcion' => 'Cómo trata Nódico los datos personales de quienes usan el espacio y el sitio.',
            'imagen'      => 'home',
        ],
        'terminos' => [
            'titulo'      => 'Términos y condiciones',
            'descripcion' => 'Condiciones de uso del espacio, de las membresías y de la renta de salones de Nódico.',
            'imagen'      => 'home',
        ],
    ],
];
