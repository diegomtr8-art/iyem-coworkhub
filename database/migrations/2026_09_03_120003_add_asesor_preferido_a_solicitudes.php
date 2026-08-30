<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4.D — el miembro puede indicar un asesor **preferido** al pedir asesoría.
 *
 * Es distinto de `asesor_id`, que es el asesor **asignado** cuando recepción
 * confirma. El preferido es lo que la bandeja muestra como «asesor sugerido»
 * (D.4): una preferencia, no una asignación. Si el asesor se da de baja, el
 * `nullOnDelete` deja la solicitud intacta sin sugerencia.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes_asesoria', function (Blueprint $table) {
            $table->foreignId('asesor_preferido_id')->nullable()->after('asesor_id')
                ->constrained('asesores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_asesoria', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asesor_preferido_id');
        });
    }
};
