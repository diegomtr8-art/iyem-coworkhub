<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE espacios MODIFY tipo ENUM('coworking','privado','sala_juntas','contenido','fotografia','escritorio','oficina_privada','cabina_telefonica','lounge')");

            return;
        }

        // En SQLite (pruebas) el enum se traduce a un CHECK que rechazaría los tipos
        // nuevos; se sustituye por texto libre y la validación queda en la aplicación.
        Schema::table('espacios', function (Blueprint $table) {
            $table->string('tipo')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE espacios MODIFY tipo ENUM('escritorio','oficina_privada','sala_juntas','cabina_telefonica','lounge')");
    }
};
