<?php
/**
 * Generación de imágenes derivadas.
 *
 *   php tools/imagenes.php
 *
 * IMG-06  juego de favicons desde el logo
 * IMG-09  logo blanco reexportado al tamaño en que se usa
 * IMG-08  fotos del directorio recortadas a cuadrado
 * IMG-03  tres anchos (640/1280/1920) por foto grande, para `srcset`
 */

const ANCHOS = [640, 1280, 1920];
const DIR = 'public/img/nodico';

function abrir(string $ruta)
{
    return match (strtolower(pathinfo($ruta, PATHINFO_EXTENSION))) {
        'webp'        => @imagecreatefromwebp($ruta),
        'png'         => @imagecreatefrompng($ruta),
        'jpg', 'jpeg' => @imagecreatefromjpeg($ruta),
        default       => false,
    };
}

function lienzo(int $w, int $h)
{
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);

    return $im;
}

function redimensionar($origen, int $nw, int $nh)
{
    $salida = lienzo($nw, $nh);
    imagecopyresampled($salida, $origen, 0, 0, 0, 0, $nw, $nh, imagesx($origen), imagesy($origen));

    return $salida;
}

/** Recorta al centro con la proporción pedida y escala. */
function recortarCuadrado($origen, int $lado)
{
    $w = imagesx($origen);
    $h = imagesy($origen);
    $l = min($w, $h);
    $x = (int) (($w - $l) / 2);
    $y = (int) (($h - $l) / 2);

    $salida = lienzo($lado, $lado);
    imagecopyresampled($salida, $origen, 0, 0, $x, $y, $lado, $lado, $l, $l);

    return $salida;
}

echo PHP_EOL;

// ── IMG-09 · logo blanco al tamaño real de uso ───────────────────────
// El original es un PNG de paleta de 1920x637 que se pinta a 44 px de alto.
// Sin el .ai no se puede vectorizar; reexportarlo a 3x el uso quita casi todo
// el peso y se ve nítido en retina.
$logo = DIR . '/logo-nodico-blanco.png';
if (is_file($logo)) {
    $antes = filesize($logo);
    $im = abrir($logo);
    if ($im) {
        $nw = 480;
        $nh = (int) round(imagesy($im) * $nw / imagesx($im));
        $out = redimensionar($im, $nw, $nh);
        imagepng($out, $logo, 9);
        imagedestroy($im);
        imagedestroy($out);
        printf("  IMG-09  logo-nodico-blanco.png  %s -> %s B  (%dx%d)%s",
            number_format($antes), number_format(filesize($logo)), $nw, $nh, PHP_EOL);
    }
}

// ── IMG-06 · favicons ────────────────────────────────────────────────
if (is_file($logo)) {
    // El logo es blanco: para el favicon se compone sobre el carbón de marca.
    $src = abrir($logo);

    foreach ([['apple-touch-icon.png', 180], ['favicon-96.png', 96], ['favicon-32.png', 32]] as [$nombre, $lado]) {
        $im = imagecreatetruecolor($lado, $lado);
        imagefill($im, 0, 0, imagecolorallocate($im, 0x2E, 0x2D, 0x2C));

        $margen = (int) round($lado * 0.14);
        $anchoDisp = $lado - 2 * $margen;
        $altoDisp = (int) round(imagesy($src) * $anchoDisp / imagesx($src));
        imagecopyresampled(
            $im, $src,
            $margen, (int) (($lado - $altoDisp) / 2),
            0, 0,
            $anchoDisp, $altoDisp,
            imagesx($src), imagesy($src)
        );

        imagepng($im, "public/$nombre", 9);
        imagedestroy($im);
        printf("  IMG-06  %-22s %6s B%s", $nombre, number_format(filesize("public/$nombre")), PHP_EOL);
    }
    imagedestroy($src);
}

// ── IMG-08 · fotos del directorio a cuadrado ─────────────────────────
$directorio = [
    'dir-ahimsa-daram.jpg', 'dir-zentto.jpg', 'dir-saboreli.webp',
    'dir-kinimitas.png', 'emprendedor-semana-salabtun.webp',
];
foreach ($directorio as $nombre) {
    $ruta = DIR . '/' . $nombre;
    if (! is_file($ruta)) {
        continue;
    }

    $im = abrir($ruta);
    if (! $im) {
        continue;
    }

    $antes = filesize($ruta);
    $w = imagesx($im);
    $h = imagesy($im);

    $out = recortarCuadrado($im, 600);
    $destino = preg_replace('/\.(jpe?g|png|webp)$/i', '.webp', $ruta);
    imagewebp($out, $destino, 86);
    imagedestroy($im);
    imagedestroy($out);

    if ($destino !== $ruta) {
        unlink($ruta);
    }

    printf("  IMG-08  %-34s %dx%d  %7s -> %6s B%s",
        $nombre, $w, $h, number_format($antes), number_format(filesize($destino)), PHP_EOL);
}

// ── IMG-03 · variantes para srcset ───────────────────────────────────
$grandes = array_filter(glob(DIR . '/*.webp') ?: [], function ($f) {
    if (preg_match('/-(640|1280|1920)\.webp$/', $f)) {
        return false;
    }
    $s = @getimagesize($f);

    return $s && max($s[0], $s[1]) > 800;
});

$generadas = 0;
$bytes = 0;
foreach ($grandes as $ruta) {
    $im = abrir($ruta);
    if (! $im) {
        continue;
    }

    $w = imagesx($im);
    $h = imagesy($im);
    $base = preg_replace('/\.webp$/', '', $ruta);

    foreach (ANCHOS as $ancho) {
        if ($ancho >= $w) {
            continue;
        }
        $nh = (int) round($h * $ancho / $w);
        $out = redimensionar($im, $ancho, $nh);
        $destino = "{$base}-{$ancho}.webp";
        imagewebp($out, $destino, 82);
        imagedestroy($out);
        $generadas++;
        $bytes += filesize($destino);
    }
    imagedestroy($im);
}

printf("%s  IMG-03  %d variantes generadas (%s B en total)%s%s",
    PHP_EOL, $generadas, number_format($bytes), PHP_EOL, PHP_EOL);
