<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Abriendo la app — Nódico</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               background: #1A1918; color: #F4F1EA; font-family: system-ui, -apple-system, sans-serif; padding: 24px; }
        main { max-width: 360px; text-align: center; }
        h1 { font-size: 24px; margin: 0 0 12px; }
        p { line-height: 1.6; color: #C9C3B6; margin: 0 0 24px; }
        a { display: inline-block; background: #FFE124; color: #1A1918; font-weight: 700; padding: 14px 28px;
            text-decoration: none; border-radius: 999px; }
    </style>
</head>
<body>
<main>
    <h1>Abriendo la app de Nódico…</h1>
    <p>Si no se abre sola, toca el botón. Este enlace solo funciona en el teléfono desde el que lo pediste.</p>
    <a href="{{ $destino }}">Abrir la app</a>
</main>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">window.location.replace(@json($destino));</script>
</body>
</html>
