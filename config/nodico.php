<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Correo del formulario "Hablemos"
    |--------------------------------------------------------------------------
    | Destinatario de los mensajes enviados desde el sitio público.
    */
    'contacto_email' => env('NODICO_CONTACTO_EMAIL', 'contacto@nodico.com.mx'),

    /*
    |--------------------------------------------------------------------------
    | Calendario de talleres (Luma)
    |--------------------------------------------------------------------------
    */
    'luma_embed' => env('NODICO_LUMA_EMBED', 'https://luma.com/embed/calendar/cal-ZE3dbDW6bLs4v7j/events?lt=dark'),
];
