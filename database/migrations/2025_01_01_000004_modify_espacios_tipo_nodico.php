<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE espacios MODIFY tipo ENUM('coworking','privado','sala_juntas','contenido','fotografia','escritorio','oficina_privada','cabina_telefonica','lounge')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE espacios MODIFY tipo ENUM('escritorio','oficina_privada','sala_juntas','cabina_telefonica','lounge')");
    }
};
