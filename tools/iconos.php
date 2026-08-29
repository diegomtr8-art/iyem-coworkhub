<?php
/**
 * IMG-07 (corregido) — los "SVG" de iconos no son vectoriales.
 *
 * Cada archivo es un <svg> de 1042x1042 que envuelve una imagen WebP embebida
 * en base64. No hay trazos, así que ni SVGO ni `currentColor` aplican: lo que
 * procede es extraer el raster y reescalarlo al tamaño en que se muestra.
 *
 * Se pintan a 56 px (h-14); se exportan a 160 px para pantallas retina.
 *
 *   php tools/iconos.php
 */

const DESTINO_PX = 160;

$dir = 'public/img/nodico';
$svgs = glob("$dir/{icono,valor}-*.svg", GLOB_BRACE) ?: [];

if (! $svgs) {
    echo PHP_EOL . '  No hay SVG que convertir (¿ya se ejecutó?).' . PHP_EOL . PHP_EOL;
    exit(0);
}

$antes = 0;
$despues = 0;
$convertidos = [];

echo PHP_EOL;

foreach ($svgs as $ruta) {
    $bytesAntes = filesize($ruta);
    $antes += $bytesAntes;

    $contenido = file_get_contents($ruta);

    if (! preg_match('#xlink:href="data:image/(webp|png|jpeg);base64,([^"]+)"#', $contenido, $m)) {
        printf("  %-40s sin raster embebido, se deja tal cual%s", basename($ruta), PHP_EOL);
        $despues += $bytesAntes;
        continue;
    }

    $binario = base64_decode($m[2]);
    $origen = @imagecreatefromstring($binario);
    if (! $origen) {
        printf("  %-40s no se pudo decodificar%s", basename($ruta), PHP_EOL);
        $despues += $bytesAntes;
        continue;
    }

    $w = imagesx($origen);
    $h = imagesy($origen);
    $escala = DESTINO_PX / max($w, $h);
    $nw = max(1, (int) round($w * $escala));
    $nh = max(1, (int) round($h * $escala));

    $salida = imagecreatetruecolor($nw, $nh);
    imagealphablending($salida, false);
    imagesavealpha($salida, true);
    imagefill($salida, 0, 0, imagecolorallocatealpha($salida, 0, 0, 0, 127));
    imagealphablending($salida, true);
    imagecopyresampled($salida, $origen, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $nuevaRuta = preg_replace('/\.svg$/', '.webp', $ruta);
    imagewebp($salida, $nuevaRuta, 88);

    imagedestroy($origen);
    imagedestroy($salida);
    unlink($ruta);

    $bytesDespues = filesize($nuevaRuta);
    $despues += $bytesDespues;
    $convertidos[] = basename($nuevaRuta);

    printf(
        "  %-40s %dx%d  %7s -> %6s B  (%d%% menos)%s",
        basename($ruta), $w, $h,
        number_format($bytesAntes), number_format($bytesDespues),
        100 - (int) round($bytesDespues * 100 / $bytesAntes),
        PHP_EOL
    );
}

printf(
    "%s  TOTAL  %s B -> %s B  (%d%% menos)%s",
    PHP_EOL,
    number_format($antes), number_format($despues),
    $antes ? 100 - (int) round($despues * 100 / $antes) : 0,
    PHP_EOL
);
printf("  %d iconos convertidos a WebP de %d px%s%s", count($convertidos), DESTINO_PX, PHP_EOL, PHP_EOL);
