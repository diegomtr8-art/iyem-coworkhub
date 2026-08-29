<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B — Bitácora de autenticación.
 *
 * Hasta ahora no quedaba rastro de nada: ni de quién entró, ni de cuántas veces
 * se falló contra una cuenta, ni de quién cambió una contraseña. Sin esto no
 * hay forma de responder a «alguien entró a mi cuenta» más que encogiéndose de
 * hombros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_auth', function (Blueprint $table) {
            $table->id();

            // Nulo a propósito: un intento contra un correo que no existe no
            // tiene usuario, y es justo el intento que más interesa registrar.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // El correo tecleado, que puede no corresponder a ninguna cuenta.
            $table->string('correo')->nullable()->index();

            $table->string('tipo', 40)->index();
            $table->boolean('exito')->default(true);
            $table->string('ip', 45)->nullable();
            $table->text('agente')->nullable();

            // Detalle suelto del evento: segundos de bloqueo, proveedor social,
            // número de sesiones cerradas. Que no obligue a migrar por cada dato.
            $table->json('contexto')->nullable();

            $table->timestamp('created_at')->useCurrent()->index();

            // La consulta de la bitácora es siempre «lo de esta persona, lo más
            // reciente primero».
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_auth');
    }
};
