<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versiones anteriores del contenido del sitio: la red de seguridad que hace
 * que alguien se atreva a usar el módulo «Página Web».
 *
 * Cada fila es cómo estaba una sección **antes** de un cambio, y quién hizo ese
 * cambio. `valor` nulo significa que antes no había nada guardado: deshacer
 * vuelve al respaldo del código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versiones_contenido_sitio', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80)->index();
            $table->json('valor')->nullable();
            $table->foreignId('guardada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versiones_contenido_sitio');
    }
};
