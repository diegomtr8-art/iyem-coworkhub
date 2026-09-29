<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración a Openpay — paso 3: el cobro automático de cada periodo.
 *
 * - `planes_pasarela`: a qué plan de Openpay corresponde cada plan de Nódico.
 *   El importe de un plan de Openpay **no se puede editar** (su API solo deja
 *   cambiar nombre y días de prueba), así que un cambio de precio crea un plan
 *   nuevo y el anterior queda inactivo: las suscripciones que ya existen
 *   siguen con su precio hasta que se renueven a mano. Sustituye a
 *   `planes.stripe_price_id` sin tocar esa columna, que Stripe sigue usando.
 * - `suscripciones_pasarela`: la suscripción de Openpay de cada miembro. La
 *   crea el servidor **después** de confirmar el primer cargo (nunca la
 *   pantalla), con la tarjeta guardada y con prueba hasta el último día del
 *   periodo pagado: Openpay cobra solo a partir del siguiente.
 * - `cargos_pasarela.suscribir`: el cargo es el primer periodo de una
 *   suscripción; al confirmarse se da de alta.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('planes_pasarela', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('planes');
            $table->string('pasarela', 20);
            $table->string('plan_pasarela_id', 45);
            $table->decimal('importe', 10, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['pasarela', 'plan_pasarela_id']);
            $table->index(['plan_id', 'pasarela', 'activo']);
        });

        Schema::create('suscripciones_pasarela', function (Blueprint $table) {
            $table->id();
            // Como `ordenes_pago`: borrar la cuenta no se lleva el registro.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('plan_id')->constrained('planes');
            $table->string('pasarela', 20);
            $table->string('suscripcion_id', 45)->unique();
            $table->string('cliente_id', 45);
            $table->string('tarjeta_id', 45)->nullable();
            $table->string('plan_pasarela_id', 45);
            // El cargo del primer periodo: una suscripción por cargo, nunca dos.
            $table->foreignId('cargo_id')->nullable()->unique()->constrained('cargos_pasarela')->nullOnDelete();

            // trial | active | past_due | unpaid | cancelled (los de Openpay)
            $table->string('estado', 20);
            $table->boolean('cancelar_al_final')->default(false);
            $table->date('periodo_fin')->nullable();
            $table->date('proximo_cobro')->nullable();
            $table->unsignedInteger('periodo_actual')->default(0);
            // Se suspendió la membresía por un cobro rechazado: al pagarse el
            // reintento, la cuenta vuelve a quedar activa.
            $table->boolean('en_impago')->default(false);
            $table->timestamp('sincronizada_en')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'pasarela', 'estado']);
        });

        Schema::table('cargos_pasarela', function (Blueprint $table) {
            $table->boolean('suscribir')->default(false)->after('operacion');
        });
    }

    public function down(): void
    {
        Schema::table('cargos_pasarela', function (Blueprint $table) {
            $table->dropColumn('suscribir');
        });

        Schema::dropIfExists('suscripciones_pasarela');
        Schema::dropIfExists('planes_pasarela');
    }
};
