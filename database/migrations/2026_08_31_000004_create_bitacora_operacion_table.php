<?php

use App\Enums\AccionOperativa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3.11 — bitácora de operación.
 *
 * Separada de `eventos_auth` a propósito: aquella responde a «¿alguien entró a
 * esta cuenta?», esta a «¿quién le tocó las horas a este miembro y por qué?».
 * Mezclarlas haría inútiles las dos, porque el ruido de los intentos de ingreso
 * enterraría los seis ajustes de horas del mes.
 *
 * `actor` es quien hizo la acción; `sujeto`, el miembro afectado. Las dos son
 * `nullOnDelete` y **no** cascade: si se borra una cuenta, su rastro operativo
 * tiene que sobrevivir; es justo entonces cuando hace falta.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bitacora_operacion', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $tabla->foreignId('sujeto_user_id')->nullable()->constrained('users')->nullOnDelete();

            $tabla->enum('accion', AccionOperativa::valores());

            // Frase legible, ya redactada: la bitácora se lee, no se interpreta.
            $tabla->string('descripcion', 500);

            // El porqué, cuando la acción lo exige (ver `AccionOperativa::exigeMotivo`).
            $tabla->text('motivo')->nullable();

            // Datos de apoyo: importes, ids, valores antes y después.
            $tabla->json('contexto')->nullable();

            $tabla->string('ip', 45)->nullable();
            $tabla->string('agente', 500)->nullable();

            // Solo creación: una entrada de bitácora no se actualiza jamás.
            $tabla->timestamp('created_at')->nullable();

            $tabla->index(['sujeto_user_id', 'created_at']);
            $tabla->index(['accion', 'created_at']);
            $tabla->index(['actor_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_operacion');
    }
};
