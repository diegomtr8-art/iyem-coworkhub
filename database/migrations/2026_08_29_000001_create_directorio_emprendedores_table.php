<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CNT-02 / CNT-03 — el directorio y el «emprendedor de la semana» estaban
 * codificados dentro de Comunidad.vue: cambiar uno obligaba a recompilar y
 * desplegar. Pasan a base de datos.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('directorio_emprendedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('instagram')->nullable();
            $table->string('foto')->nullable();
            $table->text('descripcion')->nullable();
            // CNT-03: destino del «Saber más…», que se perdió en la migración.
            $table->string('url_destino')->nullable();
            $table->boolean('destacado_semana')->default(false);
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->index(['activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directorio_emprendedores');
    }
};
