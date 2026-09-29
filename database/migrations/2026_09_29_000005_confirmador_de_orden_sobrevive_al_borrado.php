<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Borrar una cuenta de personal no puede fallar porque haya confirmado pagos.
 *
 * `ordenes_pago.confirmada_por_user_id` era `RESTRICT`: la orden de pago de
 * cualquier miembro, confirmada en caja, impedía borrar a quien la confirmó.
 * Salió al resembrar las cuentas de demostración en el servidor de pruebas
 * (29-sep-2026): `admin.demo` había confirmado una orden y `DemoSeeder` no
 * pudo borrarlo. Lo mismo le pasaría a una cuenta real de caja.
 *
 * Como con `user_id` (2026_09_23_000001): la orden se queda —con su monto,
 * referencia y fecha de confirmación, que es lo que contabilidad necesita— y
 * solo pierde el vínculo con quien la confirmó. Es lo mismo que ya hace la
 * bitácora (`actor_user_id` es `nullOnDelete`): borrar una cuenta de personal
 * borra el «quién», no el «qué».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_pago', function (Blueprint $tabla) {
            $tabla->dropForeign(['confirmada_por_user_id']);
        });

        Schema::table('ordenes_pago', function (Blueprint $tabla) {
            $tabla->foreign('confirmada_por_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_pago', function (Blueprint $tabla) {
            $tabla->dropForeign(['confirmada_por_user_id']);
        });

        Schema::table('ordenes_pago', function (Blueprint $tabla) {
            $tabla->foreign('confirmada_por_user_id')->references('id')->on('users');
        });
    }
};
