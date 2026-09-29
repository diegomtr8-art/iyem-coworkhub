<?php

namespace App\Console\Commands;

use App\Servicios\Sitio\FormatosDeImagen;
use App\Servicios\Sitio\ProcesadorDeImagenes;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Sube una imagen del sitio desde la consola, por el mismo camino que el panel.
 *
 * Sirve para comprobar un servidor nuevo (que el disco `medios` existe, se
 * puede escribir y se sirve por HTTP) sin tener que entrar al panel:
 *
 *   php artisan nodico:subir-imagen foto.jpg tarjeta
 *   curl -sI https://prueba.nodico.com.mx/medios/sitio/<carpeta>/1600.webp
 */
class SubirImagenSitio extends Command
{
    protected $signature = 'nodico:subir-imagen {archivo : Ruta de la foto} {formato : retrato, panoramica, ancha, horizontal, tarjeta o social}';

    protected $description = 'Sube una imagen al disco de medios del sitio y muestra sus URLs.';

    public function handle(ProcesadorDeImagenes $procesador): int
    {
        $ruta = $this->argument('archivo');
        $formato = $this->argument('formato');

        if (! is_file($ruta)) {
            $this->error("No existe el archivo {$ruta}.");

            return self::FAILURE;
        }

        if (! FormatosDeImagen::existe($formato)) {
            $this->error("Formato desconocido. Usa uno de: " . implode(', ', array_keys(FormatosDeImagen::todos())) . '.');

            return self::FAILURE;
        }

        try {
            $imagen = $procesador->subir(new UploadedFile($ruta, basename($ruta), null, null, true), $formato);
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $this->info("Imagen #{$imagen->id} ({$imagen->ancho}×{$imagen->alto}) en la carpeta {$imagen->carpeta}");

        foreach ($imagen->anchos as $ancho) {
            $this->line('  ' . url($imagen->url($ancho)));
        }

        return self::SUCCESS;
    }
}
