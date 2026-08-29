<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contador de la bolsa de asesoría IYEM, para que las cuatro bolsas de
 * `BolsaDeHoras` tengan su caché y `campoConsumo()` no apunte a una columna
 * que no existe.
 *
 * Las solicitudes de asesoría en sí llegan en la Fase 1.2; esto es solo el
 * contador, que es lo que el motor de bolsas ya necesita.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('suscripciones', function (Blueprint $tabla) {
            $tabla->decimal('horas_asesoria_usadas', 6, 2)->default(0)->after('horas_contenido_usadas');
        });
    }

    public function down(): void
    {
        Schema::table('suscripciones', function (Blueprint $tabla) {
            $tabla->dropColumn('horas_asesoria_usadas');
        });
    }
};
