<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrar una cuenta no puede fallar porque tenga órdenes de pago, ni puede
 * llevarse el registro fiscal por delante.
 *
 * `ordenes_pago.user_id` era `RESTRICT`: cualquiera que hubiera generado una
 * referencia recibía un 500 al borrar su cuenta (flujo que Apple exige a la
 * app). Ahora la orden se queda —con su foto fiscal, que es lo que contabilidad
 * necesita— y pierde solo el vínculo con la persona.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_pago', function (Blueprint $tabla) {
            $tabla->dropForeign(['user_id']);
        });

        Schema::table('ordenes_pago', function (Blueprint $tabla) {
            $tabla->foreignId('user_id')->nullable()->change();
            $tabla->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_pago', function (Blueprint $tabla) {
            $tabla->dropForeign(['user_id']);
        });

        Schema::table('ordenes_pago', function (Blueprint $tabla) {
            $tabla->foreignId('user_id')->nullable(false)->change();
            $tabla->foreign('user_id')->references('id')->on('users');
        });
    }
};
