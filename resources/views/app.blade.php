<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @if (app()->environment('staging', 'local'))
            <meta name="robots" content="noindex, nofollow">
        @endif

        {{-- SEO-01 — estos metadatos los ponía `Meta.vue` al montar, así que sólo
             existían después de ejecutar el JavaScript. WhatsApp, Slack,
             Telegram, LinkedIn y Twitter/X no lo ejecutan: al compartir un
             enlace no salía tarjeta, y las imágenes 1200x630 no las veía nadie.

             Van marcados con `inertia` para que el Head del cliente los adopte
             y los reemplace al navegar dentro de la SPA, en vez de duplicarlos.
             La copia sale de config/nodico.php, la misma que lee `Meta.vue`. --}}
        @php($seo = $page['props']['seo'] ?? [])
        @php($meta = $seo['pagina'] ?? [])
        @php($tituloCompleto = trim(($meta['titulo'] ?? '') . ' — ' . config('nodico.sufijo_titulo'), ' —'))

        <title inertia>{{ $tituloCompleto }}</title>
        <meta name="description" content="{{ $meta['descripcion'] ?? '' }}" inertia>
        <link rel="canonical" href="{{ $seo['canonica'] ?? '' }}" inertia>

        <meta property="og:type" content="website" inertia>
        <meta property="og:site_name" content="Nódico" inertia>
        <meta property="og:locale" content="es_MX" inertia>
        <meta property="og:title" content="{{ $tituloCompleto }}" inertia>
        <meta property="og:description" content="{{ $meta['descripcion'] ?? '' }}" inertia>
        <meta property="og:url" content="{{ $seo['canonica'] ?? '' }}" inertia>
        <meta property="og:image" content="{{ $meta['imagen'] ?? '' }}" inertia>
        <meta property="og:image:width" content="1200" inertia>
        <meta property="og:image:height" content="630" inertia>
        <meta property="og:image:alt" content="{{ $tituloCompleto }}" inertia>

        <meta name="twitter:card" content="summary_large_image" inertia>
        <meta name="twitter:title" content="{{ $tituloCompleto }}" inertia>
        <meta name="twitter:description" content="{{ $meta['descripcion'] ?? '' }}" inertia>
        <meta name="twitter:image" content="{{ $meta['imagen'] ?? '' }}" inertia>

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
