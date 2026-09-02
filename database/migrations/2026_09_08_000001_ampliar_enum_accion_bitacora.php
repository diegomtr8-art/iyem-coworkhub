<?php

use App\Enums\AccionOperativa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía el enum `bitacora_operacion.accion` a TODOS los valores actuales.
 *
 * Se añadieron acciones nuevas al enum de PHP (vinculacion_rostro, apertura_
 * puerta) pero la columna ENUM de MySQL seguía con los valores viejos: cualquier
 * registro con una acción nueva daba «Data truncated» → 500. Por eso abrir la
 * puerta desde el panel fallaba (el comando se creaba, pero la bitácora tronaba).
 *
 * Se reconstruye desde AccionOperativa::valores() para que quede siempre al día.
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
