<?php

use App\Enums\TipoEspacio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-06 — el catálogo de `espacios.tipo` tenía diez valores para seis espacios
 * reales, con duplicados (`privado` y `oficina_privada` son lo mismo) y cuatro
 * tipos muertos heredados del esquema original.
 *
 * Se migran los datos existentes a los tipos vivos y se recorta el enum. Las
 * equivalencias viven en `TipoEspacio::equivalenciasHistoricas()` para que la
 * migración y el código digan lo mismo.
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (TipoEspacio::equivalenciasHistoricas() as $muerto => $vivo) {
            $migradas = DB::table('espacios')->where('tipo', $muerto)->update(['tipo' => $vivo]);

            if ($migradas > 0) {
                echo "  espacios: {$migradas} fila(s) «{$muerto}» → «{$vivo}»" . PHP_EOL;
            }
        }

        // Cualquier tipo que no esté en el catálogo vivo ni en las equivalencias
        // se queda como está y quedaría fuera del enum: hay que verlo, no
        // taparlo con un valor por defecto inventado.
        $huerfanos = DB::table('espacios')
            ->whereNotIn('tipo', TipoEspacio::valores())
            ->pluck('tipo')
            ->unique();

        if ($huerfanos->isNotEmpty()) {
            throw new RuntimeException(
                'Hay espacios con un tipo que no está en el catálogo ni tiene equivalencia: '
                . $huerfanos->implode(', ') . '. Revísalos antes de migrar.'
            );
        }

        $this->fijarCatalogo(TipoEspacio::valores());
    }

    public function down(): void
    {
        // El enum vuelve a admitir los tipos muertos. Los datos **no** se
        // deshacen: no hay forma de saber cuál de los `privado` era antes
        // `oficina_privada` y cuál `cabina_telefonica`.
        $this->fijarCatalogo(array_merge(
            TipoEspacio::valores(),
            array_keys(TipoEspacio::equivalenciasHistoricas()),
        ));
    }

    /**
     * En SQLite (pruebas) la columna es TEXT y la validación queda en la
     * aplicación; el CHECK que genera Laravel rechazaría cualquier valor nuevo.
     */
    private function fijarCatalogo(array $valores): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('espacios', function (Blueprint $tabla) {
                $tabla->string('tipo')->change();
            });

            return;
        }

        $lista = implode(',', array_map(fn ($v) => "'" . $v . "'", $valores));

        DB::statement("ALTER TABLE espacios MODIFY tipo ENUM({$lista}) NOT NULL");
    }
};
