<?php

namespace App\Servicios\Sitio;

use App\Models\ImagenSitio;
use App\Models\User;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Convierte la foto que sube la coordinación en el juego de archivos que usa
 * el sitio: recortada a la proporción de su hueco, bien orientada, en varios
 * anchos y sin metadatos.
 *
 * Al volver a codificar con GD se pierde el EXIF, incluida la ubicación GPS que
 * traen las fotos de celular: el sitio no publica dónde se tomó cada foto.
 *
 * Nunca borra la imagen anterior de un hueco. El contenido apunta a la nueva
 * por id, y deshacer necesita que la vieja siga ahí.
 */
class ProcesadorDeImagenes
{
    public const PESO_MAXIMO_KB = 15 * 1024;

    /**
     * Un JPG de 2 MB puede declarar 30 000 × 30 000 píxeles y pedir 3,6 GB de
     * memoria al abrirlo. El tope va antes de decodificar, con lo que dice la
     * cabecera. 50 MP cubre la foto más grande de un celular actual.
     */
    public const MEGAPIXELES_MAXIMOS = 50;

    private const TIPOS = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Solo se sirven las extensiones que genera este procesador. Todo lo demás
     * en la carpeta, empezando por cualquier .php, responde 403.
     */
    private const HTACCESS = <<<'HT'
    # Imágenes subidas desde el panel («Página Web»). Lo escribe ProcesadorDeImagenes.
    # Aquí solo se sirven fotos: ni scripts, ni HTML, ni listados de carpeta.
    Options -Indexes
    Require all denied
    <FilesMatch "\.(webp|jpg)$">
        Require all granted
    </FilesMatch>
    <IfModule mod_php.c>
        php_flag engine off
    </IfModule>
    <IfModule mod_headers.c>
        Header set X-Content-Type-Options "nosniff"
    </IfModule>
    HT;

    /** @throws ValidationException */
    public function subir(UploadedFile $archivo, string $formato, ?User $autor = null): ImagenSitio
    {
        $especificacion = FormatosDeImagen::de($formato);

        // `mimetypes` mira el contenido del archivo, no la extensión: un .php
        // renombrado a .jpg no pasa de aquí.
        Validator::make(['imagen' => $archivo], [
            'imagen' => ['required', 'file', 'max:' . self::PESO_MAXIMO_KB, 'mimetypes:' . implode(',', self::TIPOS)],
        ], [
            'imagen.max'       => 'La foto pesa más de 15 MB. Expórtala más ligera e inténtalo de nuevo.',
            'imagen.mimetypes' => 'El archivo tiene que ser una foto JPG, PNG o WebP.',
        ])->validate();

        $ruta = $archivo->getRealPath();

        // Segunda comprobación, sobre los bytes y sin pasar por el objeto de la
        // subida: `mimetypes` confía en lo que este le diga, y no todos los
        // caminos (el comando, un archivo falso) miran el contenido.
        if (! in_array((new \finfo(FILEINFO_MIME_TYPE))->file($ruta), self::TIPOS, true)) {
            $this->fallar('El archivo tiene que ser una foto JPG, PNG o WebP.');
        }

        $info = @getimagesize($ruta);

        if (! $info || ! in_array($info['mime'], self::TIPOS, true)) {
            $this->fallar('No se pudo leer la foto. Puede que el archivo esté dañado.');
        }

        $orientacion = $this->orientacionExif($ruta, $info['mime']);
        [$ancho, $alto] = in_array($orientacion, [5, 6, 7, 8], true) ? [$info[1], $info[0]] : [$info[0], $info[1]];

        $megapixeles = $ancho * $alto / 1_000_000;

        if ($megapixeles > self::MEGAPIXELES_MAXIMOS) {
            $this->fallar(sprintf('La foto tiene %d megapíxeles; el máximo son %d. Redúcela antes de subirla.', $megapixeles, self::MEGAPIXELES_MAXIMOS));
        }

        [$anchoRecorte, $altoRecorte] = $this->recorte($ancho, $alto, $especificacion['proporcion']);

        if ($anchoRecorte < $especificacion['minimo']) {
            $this->fallar(sprintf(
                'Esta foto es demasiado pequeña para este lugar: recortada a %s queda de %d px de ancho y hacen falta al menos %d px. Sube una foto más grande.',
                $especificacion['nombre'], $anchoRecorte, $especificacion['minimo'],
            ));
        }

        $this->asegurarMemoria();

        $imagen = @imagecreatefromstring((string) file_get_contents($ruta));

        if (! $imagen instanceof GdImage) {
            $this->fallar('No se pudo abrir la foto. Prueba a exportarla de nuevo como JPG.');
        }

        $imagen = $this->orientar($imagen, $orientacion);
        $recortada = imagecrop($imagen, [
            'x'      => intdiv(imagesx($imagen) - $anchoRecorte, 2),
            'y'      => intdiv(imagesy($imagen) - $altoRecorte, 2),
            'width'  => $anchoRecorte,
            'height' => $altoRecorte,
        ]);
        imagedestroy($imagen);

        $anchos = $this->anchos($especificacion['anchos'], $anchoRecorte);
        [$pAncho, $pAlto] = $especificacion['proporcion'];
        $carpeta = (string) Str::uuid();
        $disco = Storage::disk('medios');

        $this->blindar($disco);

        try {
            foreach ($anchos as $destino) {
                $variante = $this->escalar($recortada, $destino, (int) round($destino * $pAlto / $pAncho));
                $disco->put(
                    "sitio/{$carpeta}/{$destino}.{$especificacion['extension']}",
                    $this->codificar($variante, $especificacion['extension'], $especificacion['calidad']),
                );
                imagedestroy($variante);
            }

            $mayor = end($anchos);

            return ImagenSitio::create([
                'formato'         => $formato,
                'carpeta'         => $carpeta,
                'extension'       => $especificacion['extension'],
                'anchos'          => $anchos,
                'ancho'           => $mayor,
                'alto'            => (int) round($mayor * $pAlto / $pAncho),
                'nombre_original' => Str::limit($archivo->getClientOriginalName(), 250, ''),
                'peso_original'   => $archivo->getSize(),
                'subida_por'      => $autor?->id,
            ]);
        } catch (\Throwable $e) {
            // Nada a medias: o el juego completo con su fila, o nada.
            $disco->deleteDirectory("sitio/{$carpeta}");

            throw $e;
        } finally {
            imagedestroy($recortada);
        }
    }

    /**
     * El mayor recorte centrado con la proporción pedida.
     *
     * @param  array{int, int}  $proporcion
     * @return array{int, int}
     */
    private function recorte(int $ancho, int $alto, array $proporcion): array
    {
        [$pAncho, $pAlto] = $proporcion;

        if ($ancho * $pAlto > $alto * $pAncho) {
            return [intdiv($alto * $pAncho, $pAlto), $alto];
        }

        return [$ancho, intdiv($ancho * $pAlto, $pAncho)];
    }

    /**
     * Los anchos del formato que caben en la foto, más la foto recortada entera
     * si es menor que el más grande. Nunca se amplía.
     *
     * @param  array<int, int>  $anchos
     * @return array<int, int>
     */
    private function anchos(array $anchos, int $disponible): array
    {
        $caben = array_filter($anchos, fn (int $ancho) => $ancho <= $disponible);
        $caben[] = min($disponible, max($anchos));

        $caben = array_values(array_unique($caben));
        sort($caben);

        return $caben;
    }

    private function escalar(GdImage $origen, int $ancho, int $alto): GdImage
    {
        $destino = imagecreatetruecolor($ancho, $alto);
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $ancho, $alto, imagesx($origen), imagesy($origen));

        return $destino;
    }

    private function codificar(GdImage $imagen, string $extension, int $calidad): string
    {
        if ($extension === 'jpg') {
            // JPG no tiene transparencia: un PNG con fondo transparente saldría
            // con el fondo negro. Se aplana sobre blanco.
            $plana = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
            imagefill($plana, 0, 0, imagecolorallocate($plana, 255, 255, 255));
            imagecopy($plana, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));
            $imagen = $plana;
        }

        ob_start();
        $ok = $extension === 'jpg' ? imagejpeg($imagen, null, $calidad) : imagewebp($imagen, null, $calidad);
        $bytes = (string) ob_get_clean();

        if (! $ok || $bytes === '') {
            throw new \RuntimeException("GD no pudo generar el {$extension}. ¿Tiene soporte para ese formato este PHP?");
        }

        return $bytes;
    }

    /** Las fotos de celular vienen de lado con una marca EXIF en vez de giradas. */
    private function orientacionExif(string $ruta, string $mime): int
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($ruta);

        return (int) ($exif['Orientation'] ?? 1);
    }

    private function orientar(GdImage $imagen, int $orientacion): GdImage
    {
        // imagerotate gira en sentido contrario a las agujas del reloj.
        $girar = fn (GdImage $i, int $grados) => imagerotate($i, $grados, 0);

        return match ($orientacion) {
            2 => tap($imagen, fn ($i) => imageflip($i, IMG_FLIP_HORIZONTAL)),
            3 => $girar($imagen, 180),
            4 => tap($girar($imagen, 180), fn ($i) => imageflip($i, IMG_FLIP_HORIZONTAL)),
            5 => tap($girar($imagen, 270), fn ($i) => imageflip($i, IMG_FLIP_HORIZONTAL)),
            6 => $girar($imagen, 270),
            7 => tap($girar($imagen, 90), fn ($i) => imageflip($i, IMG_FLIP_HORIZONTAL)),
            8 => $girar($imagen, 90),
            default => $imagen,
        };
    }

    /**
     * Una foto de 50 MP ocupa unos 200 MB ya decodificada, más las variantes.
     * Si el límite de PHP es menor, se sube solo para esta petición.
     */
    private function asegurarMemoria(): void
    {
        $limite = ini_get('memory_limit');

        if ($limite !== '-1' && $this->enBytes((string) $limite) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
    }

    private function enBytes(string $valor): int
    {
        $numero = (int) $valor;

        return match (strtolower(substr(trim($valor), -1))) {
            'g'     => $numero * 1024 ** 3,
            'm'     => $numero * 1024 ** 2,
            'k'     => $numero * 1024,
            default => $numero,
        };
    }

    /** Se vuelve a escribir si falta: la carpeta se protege sola, sin depender del despliegue. */
    private function blindar(Filesystem $disco): void
    {
        if (! $disco->exists('.htaccess')) {
            $disco->put('.htaccess', self::HTACCESS . "\n");
        }
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages(['imagen' => $mensaje]);
    }
}
