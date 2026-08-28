<?php
/**
 * Medidor de contraste real para los bloques de texto sobre imagen.
 *
 * Compone el velo (plano o degradado) sobre los píxeles reales de la foto y
 * calcula el ratio WCAG del texto contra el peor píxel de la zona donde vive.
 * Estimar esto a ojo fue justo lo que dejó pasar FE-16.
 *
 *   php tools/contraste.php
 */

const TINTA = [0x1A, 0x19, 0x18];

function luminancia(array $rgb): float
{
    $c = array_map(function ($v) {
        $v /= 255;
        return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    }, $rgb);

    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

function ratio(array $a, array $b): float
{
    $la = luminancia($a);
    $lb = luminancia($b);

    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/** Mezcla $frente sobre $fondo con opacidad $alfa. */
function componer(array $frente, array $fondo, float $alfa): array
{
    return [
        $alfa * $frente[0] + (1 - $alfa) * $fondo[0],
        $alfa * $frente[1] + (1 - $alfa) * $fondo[1],
        $alfa * $frente[2] + (1 - $alfa) * $fondo[2],
    ];
}

function abrir(string $ruta)
{
    $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

    return match ($ext) {
        'webp'        => @imagecreatefromwebp($ruta),
        'png'         => @imagecreatefrompng($ruta),
        'jpg', 'jpeg' => @imagecreatefromjpeg($ruta),
        default       => false,
    };
}

/**
 * @param float|array $velo  opacidad plana, o [abajo, medio, arriba] para degradado vertical
 * @param array       $zona  [inicio, fin] en fracción de altura donde vive el texto
 */
function analizar(string $nombre, string $archivo, $velo, array $textos, array $zona = [0.0, 1.0]): void
{
    $im = abrir($archivo);
    if (! $im) {
        printf("  %-34s NO SE PUDO ABRIR (%s)%s", $nombre, $archivo, PHP_EOL);
        return;
    }

    $w = imagesx($im);
    $h = imagesy($im);
    $paso = max(1, (int) floor(min($w, $h) / 60)); // ~60 muestras por lado

    $y0 = (int) ($h * $zona[0]);
    $y1 = (int) ($h * $zona[1]);

    $peores = [];
    foreach ($textos as $etiqueta => $t) {
        $peores[$etiqueta] = ['ratio' => INF, 'fondo' => null];
    }

    for ($y = $y0; $y < $y1; $y += $paso) {
        // Alfa del velo en esta fila.
        if (is_array($velo)) {
            $p = $h > 1 ? $y / ($h - 1) : 0;          // 0 arriba, 1 abajo
            [$abajo, $medio, $arriba] = $velo;
            $alfa = $p >= 0.5
                ? $medio + ($abajo - $medio) * (($p - 0.5) * 2)
                : $arriba + ($medio - $arriba) * ($p * 2);
        } else {
            $alfa = $velo;
        }

        for ($x = 0; $x < $w; $x += $paso) {
            $rgb = imagecolorat($im, $x, $y);
            $px  = [($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255];
            $fondo = componer(TINTA, $px, $alfa);

            foreach ($textos as $etiqueta => [$color, $alfaTexto]) {
                // El texto semitransparente también se compone contra su fondo.
                $efectivo = componer($color, $fondo, $alfaTexto);
                $r = ratio($efectivo, $fondo);
                if ($r < $peores[$etiqueta]['ratio']) {
                    $peores[$etiqueta] = ['ratio' => $r, 'fondo' => $fondo];
                }
            }
        }
    }

    imagedestroy($im);

    printf("  %s  (%dx%d)%s", $nombre, $w, $h, PHP_EOL);
    foreach ($peores as $etiqueta => $d) {
        $ok = $d['ratio'] >= 4.5 ? 'OK ' : ($d['ratio'] >= 3.0 ? 'AA-grande' : 'FALLA');
        printf(
            "      %-22s peor ratio %5.2f  %-9s (peor fondo #%02X%02X%02X)%s",
            $etiqueta, $d['ratio'], $ok,
            (int) $d['fondo'][0], (int) $d['fondo'][1], (int) $d['fondo'][2],
            PHP_EOL
        );
    }
}

$B = 'public/img/nodico/';
$blanco = [255, 255, 255];

echo PHP_EOL . "=== Bloques de texto sobre imagen ===" . PHP_EOL . PHP_EOL;

analizar('Portada /nosotros CANDIDATO', $B . 'nosotros-hero.webp', [1.0, 0.80, 0.45], [
    'titular blanco'  => [$blanco, 1.0],
    'parrafo white/90' => [$blanco, 0.90],
], [0.45, 1.0]);

analizar('Vision (velo .88)', $B . 'vision.webp', 0.88, [
    'titular blanco'  => [$blanco, 1.0],
    'parrafo white/75' => [$blanco, 0.75],
]);

analizar('Beneficios portada', $B . 'nosotros-hero.webp', 0.90, [
    'titular blanco'   => [$blanco, 1.0],
    'lista blanca'     => [$blanco, 1.0],
]);

analizar('Salones portada CANDIDATO', $B . 'salon-yucatan-emprende-1.webp', [1.0, 0.80, 0.45], [
    'titular blanco'   => [$blanco, 1.0],
    'parrafo white/90' => [$blanco, 0.90],
], [0.45, 1.0]);

analizar('Teaser salones CANDIDATO', $B . 'salon-yucatan-emprende-2.webp', [1.0, 0.92, 0.60], [
    'titular blanco'   => [$blanco, 1.0],
    'parrafo white/85' => [$blanco, 0.85],
]);

analizar('Portada /actividades', $B . 'comunidad-fondo.webp', [1.0, 0.80, 0.45], [
    'titular blanco'   => [$blanco, 1.0],
    'parrafo white/90' => [$blanco, 0.90],
], [0.45, 1.0]);

analizar('Coffee break', $B . 'salon-detalle.webp', 0.90, [
    'titular blanco'   => [$blanco, 1.0],
    'texto white/55'   => [$blanco, 0.55],
]);

echo PHP_EOL;
