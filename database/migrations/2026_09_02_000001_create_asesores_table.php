<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3.7 — catálogo de asesores del IYEM.
 *
 * Decisión de Nódico (01/09/2026): los asesores son un **catálogo, no
 * usuarios**. Administración los da de alta con nombre y especialidad; no
 * tienen cuenta ni entran al sistema, y recepción los elige de una lista al
 * confirmar una solicitud.
 *
 * Con eso, `solicitudes_asesoria.asesor_user_id` —que apuntaba a `users`
 * previendo el otro escenario— queda sin sentido y se retira: una clave ajena
 * que nadie va a rellenar solo confunde a quien lea la tabla dentro de un año.
 *
 * `asesor_nombre` **se queda**, y no es duplicado: guarda el nombre tal como
 * era al confirmar. Si un asesor deja el IYEM y se da de baja del catálogo, el
 * histórico tiene que seguir diciendo quién dio aquella asesoría.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('asesores', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('nombre');
            $tabla->string('especialidad')->nullable();
            $tabla->string('email')->nullable();
            $tabla->string('telefono', 30)->nullable();
            $tabla->text('notas')->nullable();
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();

            $tabla->index(['activo', 'nombre']);
        });

        Schema::table('solicitudes_asesoria', function (Blueprint $tabla) {
            $tabla->foreignId('asesor_id')->nullable()->after('asesor_nombre')
                ->constrained('asesores')->nullOnDelete();
        });

        // Se retira la columna que preveía asesores con cuenta.
        if (Schema::hasColumn('solicitudes_asesoria', 'asesor_user_id')) {
            $huerfanas = DB::table('solicitudes_asesoria')->whereNotNull('asesor_user_id')->count();

            if ($huerfanas > 0) {
                echo "  aviso: {$huerfanas} solicitud(es) tenían asesor_user_id. "
                    . 'Su nombre se conserva en asesor_nombre.' . PHP_EOL;
            }

            Schema::table('solicitudes_asesoria', function (Blueprint $tabla) {
                $tabla->dropConstrainedForeignId('asesor_user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('solicitudes_asesoria', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('asesor_id');
            $tabla->foreignId('asesor_user_id')->nullable()->after('asesor_nombre')
                ->constrained('users')->nullOnDelete();
        });

        Schema::dropIfExists('asesores');
    }
};
