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
    | PENDIENTE: dirección, teléfono, horarios y enlace de mapa. El sitio de
    | Odoo no los publicaba en ninguna página. Mientras sigan en null, el
    | footer y la sección "Hablemos" simplemente omiten esa fila — nunca
    | muestran datos inventados.
    */
    'telefono'  => env('NODICO_TELEFONO'),
    'direccion' => env('NODICO_DIRECCION'),
    'maps_url'  => env('NODICO_MAPS_URL'),
    'horarios'  => env('NODICO_HORARIOS'),

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
];
