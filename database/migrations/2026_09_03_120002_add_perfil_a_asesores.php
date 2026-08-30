<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4.D.2 — el catálogo de colaboradores gana lo que el portal necesita para
 * presentarlos: foto, una semblanza breve y su disponibilidad general. Las
 * especialidades (qué temas imparte) viven en la tabla pivote `asesor_tema`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('asesores', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('nombre');
            $table->text('semblanza')->nullable()->after('especialidad');
            $table->string('disponibilidad')->nullable()->after('semblanza');
        });
    }

    public function down(): void
    {
        Schema::table('asesores', function (Blueprint $table) {
            $table->dropColumn(['foto', 'semblanza', 'disponibilidad']);
        });
    }
};
