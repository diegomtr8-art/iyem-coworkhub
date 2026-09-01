<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 — el amarre entre Nódico y Smart Pass.
 *
 * `smartpass_person_id` es el `tdx_person.id` del terminal facial. Es lo único que
 * une un evento de acceso (`eventos_acceso.person_id`) con el miembro de Nódico:
 * sin él, un `person_id = 147` no significa nada. Va indexado porque la ingesta lo
 * busca por cada evento reconocido.
 *
 * Es `nullable`: la mayoría de los usuarios no tiene rostro enrolado, y el enrolado
 * (que lo llena) es la Fase 3.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->unsignedBigInteger('smartpass_person_id')->nullable()->index()->after('face_id_ok');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            $tabla->dropColumn('smartpass_person_id');
        });
    }
};
