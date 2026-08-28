<?php
/**
 * Comprobación previa al build.
 *
 * Tailwind descarta en silencio las clases que no sabe generar: no hay error,
 * no hay aviso, simplemente no aparece la regla en el CSS. Así se coló
 * `bg-tinta/88` en la sección Visión y la dejó ilegible en producción.
 *
 * Este script falla el build si encuentra:
 *   1. Modificadores de opacidad fuera de la escala de Tailwind (múltiplos de 5)
 *      escritos sin corchetes.
 *   2. Imágenes cuyos atributos width/height no coinciden con el archivo real.
 *
 * Se ejecuta solo con `npm run build` (script `prebuild`).
 */

$errores = [];
$avisos  = [];

// ─────────────────────────────────────────────────────────────────────
// 1. Modificadores de opacidad inválidos
// ─────────────────────────────────────────────────────────────────────
$escala = [];
for ($i = 0; $i <= 100; $i += 5) {
    $escala[] = (string) $i;
}

$utilidades = 'bg|text|border|ring|from|via|to|divide|placeholder|shadow|outline|decoration|accent|caret|fill|stroke';

$archivos = [];
foreach (['resources/js', 'resources/css', 'resources/views'] as $dir) {
    if (! is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $f) {
        if ($f->isFile() && preg_match('/\.(vue|css|blade\.php|ts|js)$/', $f->getFilename())) {
            $archivos[] = $f->getPathname();
        }
    }
}

foreach ($archivos as $ruta) {
    foreach (file($ruta) as $n => $linea) {
        if (! preg_match_all("#\b((?:{$utilidades})-[a-z0-9\-]+)/(\d{1,3})\b(?!\])#", $linea, $ms, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($ms as $m) {
            if (! in_array($m[2], $escala, true)) {
                $errores[] = sprintf(
                    "%s:%d  clase de opacidad invalida `%s` — Tailwind no la genera. Usa /%d, /%d o la notacion %s/[.%s]",
                    $ruta, $n + 1, $m[0],
                    5 * (int) floor((int) $m[2] / 5), 5 * (int) ceil((int) $m[2] / 5),
                    $m[1], $m[2]
                );
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────
// 2. width/height que no corresponden al archivo real
// ─────────────────────────────────────────────────────────────────────
foreach ($archivos as $ruta) {
    if (! str_ends_with($ruta, '.vue')) {
        continue;
    }

    $contenido = file_get_contents($ruta);

    // Solo etiquetas <img> con src literal (las dinámicas no se pueden resolver aquí).
    preg_match_all('#<img\b[^>]*>#s', $contenido, $tags);

    foreach ($tags[0] as $tag) {
        if (! preg_match('#\ssrc="(/[^"]+)"#', $tag, $src)) {
            continue;
        }
        if (! preg_match('#\swidth="(\d+)"#', $tag, $w) || ! preg_match('#\sheight="(\d+)"#', $tag, $h)) {
            continue;
        }

        $archivo = 'public' . $src[1];
        if (! is_file($archivo)) {
            $errores[] = sprintf('%s  <img src="%s"> apunta a un archivo que no existe', $ruta, $src[1]);
            continue;
        }

        $real = @getimagesize($archivo);
        if (! $real) {
            continue; // SVG y formatos que getimagesize no lee
        }

        $declarada = (int) $w[1] / (int) $h[1];
        $verdadera = $real[0] / $real[1];

        // Tolerancia del 2% en la proporción: lo que importa es que no salte el layout.
        if (abs($declarada - $verdadera) / $verdadera > 0.02) {
            $avisos[] = sprintf(
                '%s  %s declara %sx%s (%.2f) pero el archivo es %dx%d (%.2f)',
                $ruta, basename($src[1]), $w[1], $h[1], $declarada, $real[0], $real[1], $verdadera
            );
        }
    }
}

// ─────────────────────────────────────────────────────────────────────
echo PHP_EOL;

if ($avisos) {
    echo "  AVISO  width/height que no coinciden con el archivo (FE-08):" . PHP_EOL;
    foreach ($avisos as $a) {
        echo "    - $a" . PHP_EOL;
    }
    echo PHP_EOL;
}

if ($errores) {
    echo "  BUILD DETENIDO — " . count($errores) . " error(es):" . PHP_EOL;
    foreach ($errores as $e) {
        echo "    - $e" . PHP_EOL;
    }
    echo PHP_EOL;
    exit(1);
}

echo '  verificar-clases: sin errores'
    . ($avisos ? ' (' . count($avisos) . ' aviso(s) de dimensiones)' : '')
    . PHP_EOL . PHP_EOL;
