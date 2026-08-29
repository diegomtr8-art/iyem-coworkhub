<?php
/**
 * SEO-01 — imágenes sociales de 1200x630, una por página.
 *
 *   php tools/og.php
 *
 * Se componen a partir de fotos que ya existen del espacio: recorte al centro,
 * velo oscuro de marca, franja amarilla inferior y el logo blanco. No inventan
 * material: son las mismas fotos del sitio.
 */

const ANCHO = 1200;
const ALTO = 630;

$paginas = [
    'home'        => ['hero-inicio.webp',              'Coworking en Mérida para emprendedores'],
    'nosotros'    => ['nosotros-hero.webp',            '¿Quiénes somos?'],
    'membresias'  => ['daypass-emprendedor.webp',      'Membresías desde $79 MXN'],
    'eventos'     => ['salon-yucatan-emprende-1.webp', 'Salones para eventos'],
    'actividades' => ['comunidad-fondo.webp',          'Comunidad y talleres'],
];

function abrir(string $ruta)
{
    return match (strtolower(pathinfo($ruta, PATHINFO_EXTENSION))) {
        'webp'        => @imagecreatefromwebp($ruta),
        'png'         => @imagecreatefrompng($ruta),
        'jpg', 'jpeg' => @imagecreatefromjpeg($ruta),
        default       => false,
    };
}

$destino = 'public/img/og';
if (! is_dir($destino)) {
    mkdir($destino, 0755, true);
}

$logo = @imagecreatefrompng('public/img/nodico/logo-nodico-blanco.png');

echo PHP_EOL;

foreach ($paginas as $clave => [$foto, $texto]) {
    $origen = abrir("public/img/nodico/$foto");
    if (! $origen) {
        printf("  %-14s no se pudo abrir %s%s", $clave, $foto, PHP_EOL);
        continue;
    }

    $lienzo = imagecreatetruecolor(ANCHO, ALTO);

    // Recorte al centro conservando proporción.
    $ow = imagesx($origen);
    $oh = imagesy($origen);
    $escala = max(ANCHO / $ow, ALTO / $oh);
    $nw = (int) ceil($ow * $escala);
    $nh = (int) ceil($oh * $escala);
    imagecopyresampled(
        $lienzo, $origen,
        (int) ((ANCHO - $nw) / 2), (int) ((ALTO - $nh) / 2),
        0, 0, $nw, $nh, $ow, $oh
    );
    imagedestroy($origen);

    // Velo de marca para que el logo y la franja se lean sobre cualquier foto.
    $velo = imagecreatetruecolor(ANCHO, ALTO);
    imagefill($velo, 0, 0, imagecolorallocate($velo, 0x1A, 0x19, 0x18));
    imagecopymerge($lienzo, $velo, 0, 0, 0, 0, ANCHO, ALTO, 55);
    imagedestroy($velo);

    // Franja amarilla inferior: el gesto de marca más reconocible.
    $amarillo = imagecolorallocate($lienzo, 0xFF, 0xE1, 0x24);
    imagefilledrectangle($lienzo, 0, ALTO - 18, ANCHO, ALTO, $amarillo);

    // Logo blanco arriba a la izquierda.
    if ($logo) {
        $lw = 260;
        $lh = (int) round(imagesy($logo) * $lw / imagesx($logo));
        imagecopyresampled($lienzo, $logo, 72, 64, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
    }

    imagejpeg($lienzo, "$destino/$clave.jpg", 86);
    imagedestroy($lienzo);

    printf("  %-14s %-34s %7s B%s", $clave, $texto, number_format(filesize("$destino/$clave.jpg")), PHP_EOL);
}

if ($logo) {
    imagedestroy($logo);
}

echo PHP_EOL;
