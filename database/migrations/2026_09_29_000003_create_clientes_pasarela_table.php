<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración a Openpay — el cliente de cada miembro en la pasarela, y la
 * tarjeta que dejó guardada para los cobros de cada periodo.
 *
 * Tabla aparte y no una columna `openpay_id` en `users`: mientras dure la
 * migración conviven pasarelas (una misma persona puede tener cliente en
 * Stripe, en Openpay y en BBVA), y el siguiente cambio de banco no toca
 * `users`.
 *
 * **La tarjeta vive en Openpay, no aquí.** Aquí solo su identificador y lo que
 * se le enseña a la persona (marca, últimos 4, vencimiento), igual que Cashier
 * guardaba `pm_type` y `pm_last_four`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('clientes_pasarela', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('pasarela', 20);
            $table->string('cliente_id', 45);

            $table->string('tarjeta_id', 45)->nullable();
            $table->string('tarjeta_marca', 30)->nullable();
            $table->string('tarjeta_ultimos4', 4)->nullable();
            $table->string('tarjeta_vence', 5)->nullable(); // MM/AA

            $table->timestamps();

            $table->unique(['user_id', 'pasarela']);
            $table->unique(['pasarela', 'cliente_id']);
        });

        Schema::table('cargos_pasarela', function (Blueprint $table) {
            // El cargo se hizo a un cliente: se consulta por su ruta
            // (`/customers/{id}/charges/{tx}`).
            $table->string('cliente_pasarela_id', 45)->nullable()->after('transaccion_id');
        });
    }

    public function down(): void
    {
        Schema::table('cargos_pasarela', function (Blueprint $table) {
            $table->dropColumn('cliente_pasarela_id');
        });

        Schema::dropIfExists('clientes_pasarela');
    }
};
