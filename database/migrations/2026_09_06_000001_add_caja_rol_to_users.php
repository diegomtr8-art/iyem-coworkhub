<?php

use App\Enums\RolUsuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuarto rol: `caja` (mostrador de cobros). Amplía el enum `users.tipo`.
 *
 * Hay usuarios reales en prueba.nodico.com.mx: el ALTER solo agrega el valor al
 * enum, no toca ninguna fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE users MODIFY tipo ENUM('admin','staff','caja','miembro') NOT NULL DEFAULT 'miembro'"
            );
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('tipo', RolUsuario::valores())
                    ->default(RolUsuario::Miembro->value)
                    ->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE users MODIFY tipo ENUM('admin','staff','miembro') NOT NULL DEFAULT 'miembro'"
            );
        }
    }
};
