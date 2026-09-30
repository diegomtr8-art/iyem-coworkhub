<?php

use App\Enums\AccionOperativa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía `bitacora_operacion.accion` con las acciones del módulo «Página Web»
 * (edicion_sitio, deshacer_sitio, restablecer_sitio). Igual que las anteriores:
 * se reconstruye el ENUM desde AccionOperativa::valores(), así queda al día con
 * lo que haya en el código y no da «Data truncated» al registrar.
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
