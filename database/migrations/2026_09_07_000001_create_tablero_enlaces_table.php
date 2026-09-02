<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaces públicos del tablero de ocupación (Fase 6).
 *
 * Una televisión colgada no puede iniciar sesión. Se abre con un token largo y
 * aleatorio en la URL, de SOLO LECTURA, que se genera desde el panel y se puede
 * revocar. El token no autoriza nada más que ver el estado de los espacios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tablero_enlaces', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('token', 64)->unique();
            $tabla->string('nombre')->nullable();      // «Tele recepción», etc.
            $tabla->timestamp('ultimo_uso_en')->nullable();
            $tabla->timestamp('revocado_en')->nullable();
            $tabla->foreignId('creado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $tabla->timestamps();

            $tabla->index('revocado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tablero_enlaces');
    }
};
