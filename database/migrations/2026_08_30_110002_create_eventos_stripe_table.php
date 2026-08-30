<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4.A — idempotencia de los webhooks de Stripe.
 *
 * Stripe **reintenta** cada webhook hasta recibir un 2xx, y el mismo evento
 * puede llegar varias veces. Si cada llegada abriera un ciclo de horas, un
 * reintento regalaría el doble de bolsa. El identificador de evento de Stripe
 * es único y estable, así que se guarda al procesarlo y los repetidos se
 * descartan: el índice único es la garantía, no una convención.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('eventos_stripe', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('tipo');
            $table->timestamp('procesado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_stripe');
    }
};
