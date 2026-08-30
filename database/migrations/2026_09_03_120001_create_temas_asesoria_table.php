<?php

use App\Enums\CategoriaTema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4.D — catálogo de temas de asesoría IYEM.
 *
 * Hasta ahora el miembro escribía el tema a mano; esto le da una oferta que
 * explorar. Los temas los administra el panel. `validado_iyem` distingue la
 * oferta que el IYEM ya revisó de la que se sembró como punto de partida: son
 * sus servicios, no se dan por definitivos hasta que los aprueben.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('temas_asesoria', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('descripcion_corta')->nullable();
            $table->enum('categoria', CategoriaTema::valores());
            $table->unsignedSmallInteger('duracion_min')->default(60); // duración sugerida
            $table->boolean('activo')->default(true);
            $table->boolean('validado_iyem')->default(false);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });

        // Especialidades: qué temas imparte cada asesor.
        Schema::create('asesor_tema', function (Blueprint $table) {
            $table->foreignId('asesor_id')->constrained('asesores')->cascadeOnDelete();
            $table->foreignId('tema_asesoria_id')->constrained('temas_asesoria')->cascadeOnDelete();
            $table->primary(['asesor_id', 'tema_asesoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asesor_tema');
        Schema::dropIfExists('temas_asesoria');
    }
};
