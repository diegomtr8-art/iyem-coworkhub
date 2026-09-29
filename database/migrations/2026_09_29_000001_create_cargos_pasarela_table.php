<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración a BBVA — los cargos con tarjeta que Nódico manda a cobrar.
 *
 * Ecommerce BBVA no tiene webhooks: la verdad de un pago se obtiene
 * **consultando el cargo** con la llave privada (docs/PAGOS-BBVA.md §3). Esta
 * tabla es lo que hace segura esa consulta. La fila se crea **antes** de mandar
 * a la persona al banco, y el confirmador solo acepta ids que estén aquí, de
 * esa persona y por el importe que se le pidió. Sin ella, el id de un Day-Pass
 * de $79 pegado en la URL de regreso activaría Nodo Pro.
 *
 * Nombre neutro a propósito: el siguiente cambio de pasarela reutiliza la
 * tabla.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cargos_pasarela', function (Blueprint $table) {
            $table->id();
            // Como `ordenes_pago`: borrar la cuenta no se lleva el registro de
            // un cobro; solo pierde el vínculo con la persona.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('plan_id')->constrained('planes');
            $table->string('pasarela', 20);

            // `order_id` lo generamos nosotros y la pasarela lo exige único:
            // un reintento de la misma fila choca en vez de cobrar dos veces.
            $table->string('order_id', 100)->unique();
            // Lo asigna la pasarela al crear el cargo.
            $table->string('transaccion_id', 45)->nullable()->unique();

            // Pesos con dos decimales: lo que se pidió cobrar. El confirmador
            // exige que la pasarela diga exactamente esto.
            $table->decimal('importe', 10, 2);
            $table->string('moneda', 3)->default('MXN');

            // creando | pendiente | completado | fallido | cancelado |
            // abandonado | devuelto | en_revision
            $table->string('estado', 20)->default('creando');
            $table->string('estado_pasarela', 30)->nullable();

            // alta | renovacion — lo que se hizo al confirmar.
            $table->string('operacion', 20)->nullable();

            // web | app — a dónde regresa la persona desde el banco.
            $table->string('origen', 10)->default('web');
            $table->text('url_pago')->nullable();
            // Solo app: a qué dirección de la app volver (`nodico://…`, o
            // `exp://…` en Expo Go). Se valida al guardarla.
            $table->string('url_vuelta', 500)->nullable();
            // La API exige `X-Forwarded-For` con la IP del cliente también al
            // consultar; el proceso programado no tiene cliente, usa esta.
            $table->string('ip_cliente', 45)->nullable();

            $table->string('tarjeta_marca', 30)->nullable();
            $table->string('tarjeta_ultimos4', 4)->nullable();
            $table->string('error_codigo', 20)->nullable();
            $table->text('error_mensaje')->nullable();

            $table->timestamp('ultima_consulta_en')->nullable();
            $table->timestamp('confirmado_en')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
            $table->index(['user_id', 'plan_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos_pasarela');
    }
};
