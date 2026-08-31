<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Acceso con Google
    |--------------------------------------------------------------------------
    | El `redirect` **no** se toma nunca de la peticion: se compone aqui, contra
    | el host canonico. Un `redirect_uri` que venga del request convierte el
    | flujo en un redirector abierto.
    |
    | Esta misma URL tiene que estar dada de alta en Google Cloud Console, en
    | «URIs de redireccionamiento autorizados» del ID de cliente OAuth.
    */
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

    /*
    | Fase 4.G.3 — Instagram Graph API (cuenta de empresa @nodicomx vinculada a
    | una página de Facebook). El token de larga duración caduca a los 60 días;
    | se renueva con `nodico:renovar-token-instagram` (cron). Sin token, el feed
    | cae a la reja curada de la tabla `ajustes`. Ver docs/INSTAGRAM.md.
    */
    'instagram' => [
        // El token vigente vive en la tabla `ajustes` (clave `instagram_token`)
        // para que el comando de renovación pueda actualizarlo sin tocar el .env;
        // este env es solo el valor de arranque. Lo resuelve FeedDeInstagram.
        'token'        => env('INSTAGRAM_TOKEN'),
        'user_id'      => env('INSTAGRAM_USER_ID'),
        'app_id'       => env('INSTAGRAM_APP_ID'),
        'app_secret'   => env('INSTAGRAM_APP_SECRET'),
        'cache_min'    => (int) env('INSTAGRAM_CACHE_MIN', 60),
        'avisar_a'     => env('INSTAGRAM_AVISAR_A', env('MAIL_FROM_ADDRESS')),
    ],

];
