<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @if (app()->environment('staging', 'local'))
            <meta name="robots" content="noindex, nofollow">
        @endif

        <title inertia>{{ config('app.name', 'Nódico') }}</title>

        <link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
        <link rel="icon" href="/favicon-96.png" sizes="96x96" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#FFE124">

        {{-- Los dos pesos del primer pintado: titular (800) y cuerpo (400).
             El resto los pide el navegador solo si los necesita. --}}
        <link rel="preload" href="/fonts/carmen-sans-extrabold.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="/fonts/gteestiprodisplay-regular.woff2" as="font" type="font/woff2" crossorigin>

        @routes
        @vite('resources/js/app.js')
        @inertiaHead
    </head>
    <body class="font-body antialiased">
        @inertia
    </body>
</html>
