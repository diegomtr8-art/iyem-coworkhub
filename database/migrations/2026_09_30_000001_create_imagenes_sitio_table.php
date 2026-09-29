<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imágenes subidas desde el módulo «Página Web».
 *
 * No es una tabla de clave/valor: cada fila es un juego de archivos ya
 * recortado y convertido. El contenido del sitio (en `ajustes`) apunta a ellas
 * por id, con su texto alternativo, así que reemplazar una foto no borra la
 * anterior y deshacer puede volver a ella.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imagenes_sitio', function (Blueprint $table) {
            $table->id();
            $table->string('formato', 20);
            // Carpeta dentro del disco `medios`; un uuid, nunca el nombre original.
            $table->string('carpeta', 64)->unique();
            $table->string('extension', 5);
            // Anchos generados, de menor a mayor. El último es el original recortado.
            $table->json('anchos');
            $table->unsignedInteger('ancho');
            $table->unsignedInteger('alto');
            $table->string('nombre_original', 255)->nullable();
            $table->unsignedInteger('peso_original')->nullable();
            $table->foreignId('subida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imagenes_sitio');
    }
};
