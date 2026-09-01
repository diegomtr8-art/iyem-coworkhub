<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 — control de acceso (Smart Pass ↔ Nódico).
 *
 * Cada fila es un evento del terminal facial, tal y como el agente local lo leyó
 * de `tdx_pass_record` y lo firmó hacia acá. Es el espejo duradero de Smart Pass:
 * **Nódico conserva todo lo recibido** aunque Smart Pass llegara a borrarlo.
 *
 * `origen_id` es el `id` incremental de Smart Pass. Va **único** porque cumple dos
 * papeles a la vez: es el cursor del agente y la clave de idempotencia de la
 * ingesta —un reintento del agente no puede duplicar un check-in ni regalar días.
 *
 * `ocurrido_en` se guarda en **UTC** (el agente manda ISO 8601 con offset −06:00 de
 * Mérida y aquí se convierte). Nunca es la hora de recepción: un evento con hora
 * vieja entra con la suya.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('eventos_acceso', function (Blueprint $tabla) {
            $tabla->id();

            // El id de Smart Pass: cursor + clave de idempotencia. Único a propósito.
            $tabla->unsignedBigInteger('origen_id')->unique();

            // Hora real del evento, en UTC. Nunca la de recepción.
            $tabla->dateTime('ocurrido_en');

            // person_id / person_type pueden valer -1 («extraño»): enteros con signo.
            $tabla->integer('person_id');
            $tabla->integer('person_type');
            $tabla->boolean('reconocido')->default(false);

            $tabla->string('pass_type', 50);
            $tabla->string('sub_pass_type', 50)->nullable();
            $tabla->integer('direction');

            $tabla->unsignedBigInteger('device_id')->default(0);
            $tabla->string('device_key', 191)->nullable();

            // El miembro amarrado por `smartpass_person_id`, si lo hay. `null` cuando
            // es un extraño o un reconocido que todavía no está amarrado a un user.
            $tabla->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Si este evento derivó en un check-in del miembro.
            $tabla->boolean('genero_checkin')->default(false);

            $tabla->timestamps();

            $tabla->index('ocurrido_en');
            $tabla->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_acceso');
    }
};
