<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía la cola de comandos para el enrolado de rostros (Fase 3).
 *
 * `payload` lleva los datos que el agente necesita para enrolar en Smart Pass
 * (nombre, foto en base64, a qué perfil de Nódico amarrar). `person_id` guarda
 * el id que Smart Pass devuelve al crear, para vincularlo al perfil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comandos_acceso', function (Blueprint $tabla) {
            $tabla->longText('payload')->nullable()->after('device_id');
            $tabla->unsignedBigInteger('person_id')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('comandos_acceso', function (Blueprint $tabla) {
            $tabla->dropColumn(['payload', 'person_id']);
        });
    }
};
