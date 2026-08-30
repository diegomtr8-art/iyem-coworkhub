<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4.E — day-pass gratuito para emprendedores y artesanos del interior.
 *
 * No son miembros ni tienen cuenta: recepción los registra en el momento. Por
 * eso viven en su propia tabla y no en `users`. El objetivo es el dato que el
 * IYEM necesita para justificar el programa —cuántos vienen, de qué municipios,
 * de qué giro—, así que se guarda una ficha del visitante (para reencontrarlo si
 * vuelve) y una fila por cada visita.
 *
 * Decisión de Nódico (2026-08-30): **sin límite, solo registro**. No hay tope ni
 * aviso; se cuenta y ya.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('visitantes_interior', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('telefono', 30)->nullable();
            $table->string('municipio');            // municipio del interior
            $table->string('giro')->nullable();     // giro o tipo de artesanía
            $table->string('como_se_entero')->nullable();
            $table->timestamps();

            // Para reencontrar rápido en el mostrador por teléfono.
            $table->index('telefono');
            $table->index('municipio');
        });

        Schema::create('visitas_interior', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitante_interior_id')->constrained('visitantes_interior')->cascadeOnDelete();
            $table->date('fecha');
            $table->foreignId('registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitas_interior');
        Schema::dropIfExists('visitantes_interior');
    }
};
