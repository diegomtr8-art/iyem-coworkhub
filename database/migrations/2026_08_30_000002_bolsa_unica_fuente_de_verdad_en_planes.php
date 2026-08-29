<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-02 — `incluye_sala_juntas` y `horas_sala_mes` gobernaban la misma regla.
 * `ReservasController::create` filtraba los espacios con el primero y `store`
 * validaba el cupo con el segundo, así que un plan con `incluye_sala_juntas =
 * true` y `horas_sala_mes = null` enseñaba salas que luego no dejaba reservar.
 *
 * La bolsa se queda como única fuente de verdad y la columna se borra. El
 * permiso pasa a derivarse en `Plane::incluyeSalaJuntas()`.
 *
 * De paso entran las tres columnas de asesoría y topes diarios que el modelo de
 * bolsas ya contempla, para no dejar `$fillable` apuntando a columnas fantasma.
 */
return new class extends Migration {
    public function up(): void
    {
        // Si algún plan tenía el permiso puesto pero sin bolsa, la contradicción
        // se resuelve a favor de la bolsa: sin horas no hay acceso. Se avisa
        // porque puede ser un plan mal configurado, no un dato basura.
        $contradictorios = DB::table('planes')
            ->where('incluye_sala_juntas', true)
            ->whereNull('horas_sala_mes')
            ->pluck('nombre');

        foreach ($contradictorios as $nombre) {
            echo "  aviso: el plan «{$nombre}» decía incluir salas pero no tenía bolsa de horas."
                . ' Queda sin acceso a salas.' . PHP_EOL;
        }

        Schema::table('planes', function (Blueprint $tabla) {
            $tabla->dropColumn('incluye_sala_juntas');

            $tabla->integer('horas_asesoria_mes')->nullable()->after('horas_contenido_mes');
            $tabla->integer('max_horas_contenido_dia')->nullable()->after('max_horas_sala_dia');
            $tabla->integer('max_horas_asesoria_dia')->nullable()->after('max_horas_contenido_dia');
        });
    }

    public function down(): void
    {
        Schema::table('planes', function (Blueprint $tabla) {
            $tabla->boolean('incluye_sala_juntas')->default(false)->after('acceso_24h');
            $tabla->dropColumn(['horas_asesoria_mes', 'max_horas_contenido_dia', 'max_horas_asesoria_dia']);
        });

        // Se reconstruye desde la bolsa, que es lo que debió ser siempre.
        DB::table('planes')->whereNotNull('horas_sala_mes')->update(['incluye_sala_juntas' => true]);
    }
};
