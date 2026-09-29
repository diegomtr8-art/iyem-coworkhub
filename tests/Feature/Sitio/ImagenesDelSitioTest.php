<?php

namespace Tests\Feature\Sitio;

use App\Models\ImagenSitio;
use App\Servicios\Sitio\ProcesadorDeImagenes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Fase 2 del módulo «Página Web»: la coordinación sube un archivo y el servidor
 * lo recorta, lo orienta y genera las variantes. Ver docs/CMS-PAGINA-WEB.md.
 */
class ImagenesDelSitioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('medios', ['url' => '/medios']);
    }

    private function procesador(): ProcesadorDeImagenes
    {
        return app(ProcesadorDeImagenes::class);
    }

    /** @return array{int, int, string} ancho, alto y mime de un archivo del disco */
    private function medidas(string $ruta): array
    {
        $info = getimagesizefromstring(Storage::disk('medios')->get($ruta));

        return [$info[0], $info[1], $info['mime']];
    }

    private function rechaza(UploadedFile $archivo, string $formato, string $mensaje): void
    {
        try {
            $this->procesador()->subir($archivo, $formato);
            $this->fail('Se aceptó una imagen que debía rechazarse.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($mensaje, $e->errors()['imagen'][0]);
        }

        $this->assertSame(0, ImagenSitio::count());
    }

    public function test_un_archivo_genera_sus_variantes_recortadas_en_webp(): void
    {
        // 3000×1800 es más ancha que 4:3: se recorta a 2400×1800 por el centro.
        $imagen = $this->procesador()->subir(UploadedFile::fake()->image('cubiculo.jpg', 3000, 1800), 'tarjeta');

        $this->assertSame([800, 1600], $imagen->anchos);
        $this->assertSame([1600, 1200], [$imagen->ancho, $imagen->alto]);
        $this->assertSame([800, 600, 'image/webp'], $this->medidas($imagen->ruta(800)));
        $this->assertSame([1600, 1200, 'image/webp'], $this->medidas($imagen->ruta(1600)));
        $this->assertSame('cubiculo.jpg', $imagen->nombre_original);
    }

    public function test_si_la_foto_es_menor_que_el_ancho_mayor_se_usa_entera_sin_ampliar(): void
    {
        $imagen = $this->procesador()->subir(UploadedFile::fake()->image('fondo.png', 1700, 900), 'panoramica');

        // 1700×900 → 2:1 = 1700×850. Caben 640 y 1280; 1920 no, así que el
        // mayor es la foto recortada entera.
        $this->assertSame([640, 1280, 1700], $imagen->anchos);
        $this->assertSame([1700, 850, 'image/webp'], $this->medidas($imagen->ruta(1700)));
    }

    public function test_la_tarjeta_social_sale_en_jpg_de_1200_por_630(): void
    {
        $imagen = $this->procesador()->subir(UploadedFile::fake()->image('og.png', 2000, 1500), 'social');

        $this->assertSame([1200], $imagen->anchos);
        $this->assertSame([1200, 630, 'image/jpeg'], $this->medidas($imagen->ruta(1200)));
    }

    public function test_una_foto_pequena_para_su_hueco_se_rechaza_con_un_mensaje_claro(): void
    {
        $this->rechaza(UploadedFile::fake()->image('chica.jpg', 800, 600), 'tarjeta', 'demasiado pequeña');
    }

    public function test_el_ancho_minimo_se_mide_despues_de_recortar(): void
    {
        // Muy ancha, pero recortada a 9:16 se queda en 337 px.
        $this->rechaza(UploadedFile::fake()->image('tira.jpg', 4000, 600), 'retrato', 'queda de 337 px');
    }

    public function test_un_archivo_que_no_es_imagen_se_rechaza_aunque_se_llame_jpg(): void
    {
        $falso = UploadedFile::fake()->createWithContent('foto.jpg', '<?php echo "hola"; ?>');

        $this->rechaza($falso, 'tarjeta', 'JPG, PNG o WebP');
    }

    public function test_una_imagen_con_demasiados_megapixeles_se_rechaza_antes_de_abrirla(): void
    {
        // Un PNG de pocos bytes cuya cabecera declara 10 000 × 10 000. Si se
        // decodificara pediría 400 MB; tiene que caer antes, por la cabecera.
        $ihdr = pack('NNCCCCC', 10000, 10000, 8, 2, 0, 0, 0);
        $png = "\x89PNG\r\n\x1a\n"
            . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr))
            . pack('N', 0) . 'IEND' . pack('N', crc32('IEND'));

        $this->rechaza(UploadedFile::fake()->createWithContent('bomba.png', $png), 'tarjeta', '100 megapíxeles');
    }

    public function test_la_orientacion_exif_de_las_fotos_de_celular_se_respeta(): void
    {
        // El celular guarda la foto apaisada (1600×1200) con la marca «gírala
        // 90°». Bien girada es vertical, 1200×1600, y al recortarla a 4:3 da
        // 1200 de ancho. Si se ignorara la marca saldría de 1600.
        $imagen = $this->procesador()->subir($this->jpgConOrientacion(1600, 1200, 6), 'tarjeta');

        $this->assertSame(1200, $imagen->ancho);
        $this->assertSame([1200, 900, 'image/webp'], $this->medidas($imagen->ruta(1200)));
    }

    public function test_los_metadatos_no_llegan_a_los_archivos_publicados(): void
    {
        $imagen = $this->procesador()->subir($this->jpgConOrientacion(1600, 1200, 6), 'tarjeta');

        $this->assertStringNotContainsString('Exif', Storage::disk('medios')->get($imagen->ruta(1200)));
    }

    public function test_la_carpeta_se_blinda_para_no_ejecutar_nada(): void
    {
        $this->procesador()->subir(UploadedFile::fake()->image('a.jpg', 1600, 1200), 'tarjeta');

        $htaccess = Storage::disk('medios')->get('.htaccess');

        $this->assertStringContainsString('Require all denied', $htaccess);
        $this->assertStringContainsString('Options -Indexes', $htaccess);
        $this->assertStringContainsString('\.(webp|jpg)$', $htaccess);
    }

    public function test_subir_otra_foto_no_borra_la_anterior(): void
    {
        $primera = $this->procesador()->subir(UploadedFile::fake()->image('a.jpg', 1600, 1200), 'tarjeta');
        $segunda = $this->procesador()->subir(UploadedFile::fake()->image('b.jpg', 1600, 1200), 'tarjeta');

        $this->assertNotSame($primera->carpeta, $segunda->carpeta);
        Storage::disk('medios')->assertExists($primera->ruta(1600));
        Storage::disk('medios')->assertExists($segunda->ruta(1600));
    }

    public function test_presentar_devuelve_lo_que_necesita_un_img(): void
    {
        $imagen = $this->procesador()->subir(UploadedFile::fake()->image('a.jpg', 2400, 1800), 'tarjeta');

        $this->assertSame([
            'src'    => "/medios/sitio/{$imagen->carpeta}/1600.webp",
            'srcset' => "/medios/sitio/{$imagen->carpeta}/800.webp 800w, /medios/sitio/{$imagen->carpeta}/1600.webp 1600w",
            'width'  => 1600,
            'height' => 1200,
            'alt'    => 'Cubículo privado',
        ], $imagen->presentar('Cubículo privado'));
    }

    public function test_el_comando_sube_por_el_mismo_camino(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'img') . '.jpg';
        imagejpeg(imagecreatetruecolor(1600, 1200), $ruta);

        $this->artisan('nodico:subir-imagen', ['archivo' => $ruta, 'formato' => 'tarjeta'])
            ->expectsOutputToContain('/medios/sitio/')
            ->assertSuccessful();

        $this->assertSame(1, ImagenSitio::count());
        @unlink($ruta);
    }

    /** Un JPG con un bloque EXIF mínimo que solo lleva la etiqueta Orientation. */
    private function jpgConOrientacion(int $ancho, int $alto, int $orientacion): UploadedFile
    {
        ob_start();
        imagejpeg(imagecreatetruecolor($ancho, $alto));
        $jpg = ob_get_clean();

        $tiff = 'MM' . pack('n', 42) . pack('N', 8)        // cabecera big-endian, IFD en el byte 8
            . pack('n', 1)                                   // una entrada
            . pack('nnN', 0x0112, 3, 1) . pack('nn', $orientacion, 0)
            . pack('N', 0);                                  // no hay más IFD
        $app1 = "Exif\0\0" . $tiff;
        $segmento = "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1;

        // Justo después del marcador de inicio (FFD8).
        return UploadedFile::fake()->createWithContent('celular.jpg', substr($jpg, 0, 2) . $segmento . substr($jpg, 2));
    }
}
