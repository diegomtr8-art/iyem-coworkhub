<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E — Constancia de consentimiento (LFPDPPP).
 *
 * Es lo que de verdad importa el dia que alguien lo reclame: no basta con que
 * la persona haya marcado una casilla, hay que **poder demostrar** que la marco,
 * que documento acepto, en que version, cuando y desde donde.
 *
 * Un registro por aceptacion y por documento. **No se actualiza nunca**: cada
 * aceptacion es una fila nueva, asi que queda el historial completo de todas
 * las versiones que una persona ha ido aceptando. Un booleano en `users` no
 * serviria de nada en una reclamacion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consentimientos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 'privacidad' | 'terminos'
            $table->string('documento', 30);

            // La version del texto que se acepto, tal como estaba en la
            // cabecera del .md en ese momento.
            $table->string('version', 40);

            $table->timestamp('aceptado_en');
            $table->string('ip', 45)->nullable();
            $table->text('agente')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'documento', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consentimientos');
    }
};
