<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cola de órdenes de Nódico hacia el agente de la oficina.
 *
 * Nódico vive en la nube y no alcanza el localhost de Smart Pass; el agente solo
 * hace conexiones **salientes**. Así que abrir la puerta desde el panel no es un
 * empujón directo: Nódico **encola** la orden aquí y el agente la recoge en su
 * siguiente sondeo, la ejecuta contra Smart Pass y reporta el resultado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comandos_acceso', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('tipo', 30);                 // abrir_puerta
            $tabla->unsignedBigInteger('device_id')->nullable();
            $tabla->string('estado', 20)->default('pendiente'); // pendiente|enviado|ejecutado|fallido
            $tabla->text('resultado')->nullable();
            $tabla->foreignId('solicitado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $tabla->timestamp('enviado_en')->nullable();
            $tabla->timestamp('resuelto_en')->nullable();
            $tabla->timestamps();

            $tabla->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comandos_acceso');
    }
};
