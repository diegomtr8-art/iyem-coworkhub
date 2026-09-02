<?php

use App\Enums\AccionOperativa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía `bitacora_operacion.accion` para incluir la acción nueva
 * `reconexion_torno`. Igual que la migración anterior: se reconstruye el ENUM
 * desde AccionOperativa::valores() para que quede siempre al día y no dé
 * «Data truncated» al registrar una reconexión manual del torno.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // en SQLite (pruebas) la columna es texto y ya acepta todo.
        }

        $vals = collect(AccionOperativa::valores())
            ->map(fn ($v) => "'" . $v . "'")->implode(',');

        DB::statement("ALTER TABLE bitacora_operacion MODIFY accion ENUM($vals) NOT NULL");
    }

    public function down(): void
    {
        // No se revierte: quitar valores rompería filas que ya los usan.
    }
};
