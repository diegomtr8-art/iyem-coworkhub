<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BE-03 — el teléfono se guardaba tal cual se escribiera. Se conserva lo que
 * escribió la persona en `telefono` y se añade la forma normalizada.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            $table->string('telefono_e164', 20)->nullable()->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            $table->dropColumn('telefono_e164');
        });
    }
};
