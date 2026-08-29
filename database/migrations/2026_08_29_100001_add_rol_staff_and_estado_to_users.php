<?php

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A.3 y A.5 — tercer rol `staff` y estado de cuenta separado del rol.
 *
 * Hay usuarios reales en prueba.nodico.com.mx, asi que el relleno es
 * idempotente y no se toca ninguna fila existente mas alla de darle un estado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('estado_cuenta', 20)
                ->default(EstadoCuenta::Pendiente->value)
                ->after('tipo');

            // A.2 — hace que el enlace de verificacion sea de un solo uso: entra
            // en el hash firmado y se borra al verificar, de modo que el enlace
            // gastado deja de validar contra nada.
            $table->string('verificacion_nonce', 64)->nullable()->after('remember_token');
        });

        // `tipo` era enum('admin','miembro'). MySQL necesita el ALTER explicito;
        // en SQLite la columna es TEXT con un CHECK que `->change()` rehace.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE users MODIFY tipo ENUM('admin','staff','miembro') NOT NULL DEFAULT 'miembro'"
            );
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('tipo', RolUsuario::valores())
                    ->default(RolUsuario::Miembro->value)
                    ->change();
            });
        }

        // Las cuentas que ya existen llevan tiempo usando el espacio: quedan
        // activas. Las que se creen a partir de ahora nacen pendientes por el
        // default de la columna.
        DB::table('users')->update(['estado_cuenta' => EstadoCuenta::Activa->value]);

        // `User` no implementaba `MustVerifyEmail`, asi que el middleware
        // `verified` de /dashboard nunca comprobo nada y el correo de
        // verificacion no llego a enviarse jamas. Al activarlo, las cuentas que
        // ya existen se dan por verificadas: nunca se les pidio hacerlo y
        // bloquearlas ahora las dejaria fuera de su propio portal.
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Cualquier `staff` vuelve a ser admin: es lo mas parecido a su acceso
        // anterior y no deja a nadie fuera del sistema.
        DB::table('users')
            ->where('tipo', RolUsuario::Staff->value)
            ->update(['tipo' => RolUsuario::Admin->value]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE users MODIFY tipo ENUM('admin','miembro') NOT NULL DEFAULT 'miembro'"
            );
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('tipo', ['admin', 'miembro'])->default('miembro')->change();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['estado_cuenta', 'verificacion_nonce']);
        });
    }
};
