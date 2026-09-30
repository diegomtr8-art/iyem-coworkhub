<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pruebas de servicio social (29-sep-2026): «envié el formulario y no llegó
 * correo». El envío fallaba —o no— dentro de un `try/catch` que solo escribía
 * en el log, y ese log no lo abre nadie. El error se guarda en el prospecto
 * para que el panel lo enseñe junto a la persona que escribió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contactos', function (Blueprint $tabla) {
            $tabla->string('correo_error', 500)->nullable()->after('atendido');
        });
    }

    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $tabla) {
            $tabla->dropColumn('correo_error');
        });
    }
};
