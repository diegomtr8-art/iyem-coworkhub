<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @if (app()->environment('staging', 'local'))
            <meta name="robots" content="noindex, nofollow">
        @endif

        <title inertia>{{ config('app.name', 'Nódico') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">

        {{-- Las fuentes de marca se sirven localmente desde public/fonts (ver resources/css/app.css). --}}

        @routes
        @vite('resources/js/app.js')
        @inertiaHead
    </head>
    <body class="font-body antialiased">
        @inertia
    </body>
</html>
