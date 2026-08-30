<?php

use App\Models\Plane;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4.A — el cobro ocurre dentro de Nódico. Cada plan necesita su precio en
 * Stripe (`stripe_price_id`), y saber si se cobra una vez o cada mes.
 *
 * `cobro_recurrente` no se puede deducir de `tipo`: Nódico Flex es un plan
 * mensual (`tipo = mes`) pero de **pago único** por decisión de Nódico
 * (prompt A.2). Solo Nodo Pro y Nodo Match se renuevan solos.
 *
 * El importe **no** se duplica aquí: sigue viviendo en `precio`. Esto solo
 * guarda a qué precio de Stripe corresponde, que es lo que el checkout necesita.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->string('stripe_price_id')->nullable()->after('stripe_url');
            $table->boolean('cobro_recurrente')->default(false)->after('stripe_price_id');
        });

        // Los dos planes mensuales del instituto se renuevan solos; los otros no.
        Plane::whereIn('nombre', ['Nodo Pro', 'Nodo Match'])->update(['cobro_recurrente' => true]);
    }

    public function down(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->dropColumn(['stripe_price_id', 'cobro_recurrente']);
        });
    }
};
