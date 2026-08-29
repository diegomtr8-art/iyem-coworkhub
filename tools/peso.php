<?php
/**
 * Peso de los assets que carga la portada, medido desde disco.
 *
 *   php tools/peso.php
 *
 * Resuelve las rutas /img, /fonts y /build que referencian las páginas
 * públicas y sus componentes, y suma los bytes reales. Sirve para comparar
 * antes y después de las optimizaciones de la Fase 2.
 */

function bytes(string $ruta): int
{
    return is_file($ruta) ? (int) filesize($ruta) : 0;
}

function kb(int $n): string
{
    return number_format($n / 1024, 1) . ' KB';
}

/** Archivos que participan en el render de la portada. */
$fuentesCodigo = array_merge(
    [ 'resources/js/Pages/Welcome.vue', 'resources/js/Layouts/PublicLayout.vue' ],
    glob('resources/js/Components/Public/*.vue') ?: [],
);

$referencias = [];
foreach ($fuentesCodigo as $f) {
    if (! is_file($f)) {
        continue;
    }
    preg_match_all('#["\'\(](/(?:img|fonts|icons)/[^"\'\)\s]+)#', file_get_contents($f), $m);
    foreach ($m[1] as $ruta) {
        $referencias[$ruta] = true;
    }
}

// Las fuentes se declaran en el CSS, no en los componentes.
if (is_file('resources/css/app.css')) {
    preg_match_all("#url\('([^']+)'\)#", file_get_contents('resources/css/app.css'), $m);
    foreach ($m[1] as $ruta) {
        $referencias[$ruta] = true;
    }
}

$grupos = ['imagen' => 0, 'fuente' => 0, 'icono SVG' => 0];
$detalle = [];

// El navegador descarga UNA variante por imagen, no todas: se agrupan las
// derivadas de srcset y se cuenta solo la que tocaria en cada escenario.
$familias = [];
foreach (array_keys($referencias) as $ruta) {
    if (preg_match('#^(.*)-(640|1280|1920)\.webp$#', $ruta, $m)) {
        $familias[$m[1] . '.webp'][(int) $m[2]] = $ruta;
    }
}

$movil = 0;
foreach ($familias as $base => $vars) {
    ksort($vars);
    // En movil entra la de 640 si existe.
    $elegida = $vars[640] ?? reset($vars);
    $movil += bytes('public' . $elegida);
}

foreach (array_keys($referencias) as $ruta) {
    // Las derivadas no suman al total de escritorio: ahi va la original.
    if (preg_match('#-(640|1280|1920)\.webp$#', $ruta)) {
        continue;
    }
    $archivo = 'public' . $ruta;
    $b = bytes($archivo);
    if (! $b) {
        continue;
    }

    $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
    $grupo = match (true) {
        in_array($ext, ['woff2', 'woff', 'otf', 'ttf'], true) => 'fuente',
        $ext === 'svg'                                        => 'icono SVG',
        default                                               => 'imagen',
    };

    $grupos[$grupo] += $b;
    $detalle[$grupo][basename($ruta)] = $b;
}

// Bundles del build (entrada + layout público + portada).
$js = 0;
$css = 0;
if (is_file('public/build/manifest.json')) {
    $man = json_decode(file_get_contents('public/build/manifest.json'), true);
    $claves = array_filter(array_keys($man), fn ($k) => preg_match('#(app\.js|app\.css|Welcome\.vue|PublicLayout\.vue)#', $k));
    $vistos = [];
    foreach ($claves as $k) {
        foreach (array_merge([$man[$k]['file'] ?? null], $man[$k]['css'] ?? []) as $f) {
            if (! $f || isset($vistos[$f])) {
                continue;
            }
            $vistos[$f] = true;
            $b = bytes('public/build/' . $f);
            str_ends_with($f, '.css') ? $css += $b : $js += $b;
        }
    }
}

echo PHP_EOL . '=== Peso de la portada (bytes en disco) ===' . PHP_EOL . PHP_EOL;

foreach ($grupos as $g => $b) {
    printf("  %-12s %12s%s", $g, kb($b), PHP_EOL);
    if (! empty($detalle[$g])) {
        arsort($detalle[$g]);
        foreach (array_slice($detalle[$g], 0, 5, true) as $n => $v) {
            printf("      %-42s %10s%s", $n, kb($v), PHP_EOL);
        }
        if (count($detalle[$g]) > 5) {
            printf("      … y %d más%s", count($detalle[$g]) - 5, PHP_EOL);
        }
    }
}

printf("  %-12s %12s%s", 'js', kb($js), PHP_EOL);
printf("  %-12s %12s%s", 'css', kb($css), PHP_EOL);

$total = array_sum($grupos) + $js + $css;
echo PHP_EOL . '  TOTAL escritorio: ' . kb($total) . PHP_EOL;

if ($familias) {
    // En movil, las imagenes con variantes se sustituyen por la de 640.
    $imagenesConVariante = 0;
    foreach (array_keys($familias) as $base) {
        $imagenesConVariante += bytes('public' . $base);
    }
    $totalMovil = $total - $imagenesConVariante + $movil;
    echo '  TOTAL movil (640w):   ' . kb($totalMovil) . PHP_EOL;
}

echo PHP_EOL;
