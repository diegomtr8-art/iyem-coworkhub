<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @if (app()->environment('staging', 'local'))
            <meta name="robots" content="noindex, nofollow">
        @endif

        <title inertia>Nódico</title>

        <link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
        <link rel="icon" href="/favicon-96.png" sizes="96x96" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#FFE124">

        {{-- Los dos pesos del primer pintado: titular (800) y cuerpo (400).
             El resto los pide el navegador solo si los necesita. --}}
        <link rel="preload" href="/fonts/carmen-sans-extrabold.woff2" as="font" type="font/woff2" crossorigin>
        <link rel="preload" href="/fonts/gteestiprodisplay-regular.woff2" as="font" type="font/woff2" crossorigin>

        {{-- La portada pinta sobre esta foto; sin declararla aquí no se pide
             hasta que Vue monta el hero, tres segundos más tarde. --}}
        @if (($page['component'] ?? null) === 'Welcome')
            <link
                rel="preload"
                as="image"
                href="/img/nodico/hero-inicio-1280.webp"
                imagesrcset="/img/nodico/hero-inicio-640.webp 640w, /img/nodico/hero-inicio-1280.webp 1280w, /img/nodico/hero-inicio.webp 1079w"
                imagesizes="100vw"
                fetchpriority="high"
            >
        @endif

        @routes
        @vite('resources/js/app.js')

        {{-- Módulos del componente que va a montarse: bajan en paralelo con la
             entrada en vez de esperar a que ésta se ejecute. --}}
        @foreach (\App\Support\PrecargaVite::modulos($page['component'] ?? null) as $modulo)
            <link rel="modulepreload" href="{{ $modulo }}">
        @endforeach

        @inertiaHead
    </head>
    <body class="font-body antialiased">
        @inertia
    </body>
</html>
