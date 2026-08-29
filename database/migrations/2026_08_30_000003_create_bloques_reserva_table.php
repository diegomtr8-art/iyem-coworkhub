<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-03 — la doble reserva.
 *
 * La comprobación de traslape ocurría **fuera** de la transacción: dos personas
 * reservando la misma sala a la vez pasaban ambas la comprobación y ambas
 * escribían. Meterla dentro con bloqueo pesimista arregla el caso normal, pero
 * la validación en aplicación nunca es suficiente para esto: basta un `create`
 * desde otro sitio, un comando, un seeder o una importación para volver a
 * partir la agenda.
 *
 * MySQL no tiene restricciones de exclusión sobre rangos (eso es de Postgres),
 * así que el rango se materializa: una fila por bloque de 30 minutos ocupado,
 * con **índice único sobre (espacio_id, fecha, bloque)**. Dos reservas que se
 * pisen comparten al menos un bloque, y la segunda inserción falla en la base.
 *
 * Los 30 minutos no son arbitrarios: es la granularidad de reserva que fija
 * `config('nodico.operacion.granularidad_minutos')`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bloques_reserva', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $tabla->foreignId('espacio_id')->constrained('espacios')->cascadeOnDelete();
            $tabla->date('fecha');

            // Índice del bloque de 30 min desde medianoche: 0 = 00:00, 18 = 09:00, 47 = 23:30.
            $tabla->unsignedTinyInteger('bloque');

            $tabla->timestamps();

            // La restricción que de verdad impide el traslape.
            $tabla->unique(['espacio_id', 'fecha', 'bloque'], 'bloques_reserva_unico');

            // Para liberar los bloques de una reserva al cancelarla.
            $tabla->index(['reserva_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloques_reserva');
    }
};
