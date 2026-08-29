<?php

use App\Models\Reserva;
use App\Servicios\Reservas\RegistroDeBloques;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rellena `bloques_reserva` con las reservas confirmadas que ya existían.
 *
 * Sin esto la restricción del BUG-03 solo cubre lo que se reserve a partir de
 * ahora: una reserva anterior no tendría bloques y su horario se podría volver
 * a vender. Una restricción que solo protege la mitad de las filas no protege
 * nada.
 *
 * Si dos reservas ya guardadas se pisan —cosa perfectamente posible, porque la
 * comprobación de traslape nunca funcionó: comparaba `where('fecha', '2026-08-31')`
 * contra un valor almacenado como `'2026-08-31 00:00:00'` y no casaba jamás—
 * la migración **se detiene y las enumera** en vez de tragárselas. Son un
 * conflicto de agenda real y alguien de Nódico tiene que decidir cuál vale.
 */
return new class extends Migration {
    public function up(): void
    {
        $registro = new RegistroDeBloques();
        $ocupados = [];
        $choques  = [];
        $filas    = [];
        $ahora    = now();

        $reservas = Reserva::with('espacio')
            ->where('estatus', 'Confirmada')
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        foreach ($reservas as $reserva) {
            try {
                $bloques = $registro->bloquesDe($reserva->hora_inicio, $reserva->hora_fin);
            } catch (InvalidArgumentException $e) {
                // Una reserva con horas corruptas no puede ocupar bloques, pero
                // tampoco debe tumbar el despliegue en silencio.
                $choques[] = "  reserva #{$reserva->id}: {$e->getMessage()}";
                continue;
            }

            foreach ($bloques as $bloque) {
                $clave = $reserva->espacio_id . '|' . $reserva->fecha->toDateString() . '|' . $bloque;

                if (isset($ocupados[$clave])) {
                    $choques[] = "  reserva #{$reserva->id} se pisa con la #{$ocupados[$clave]} "
                        . "({$reserva->espacio?->nombre}, {$reserva->fecha->toDateString()} "
                        . substr($reserva->hora_inicio, 0, 5) . '–' . substr($reserva->hora_fin, 0, 5) . ')';
                    continue 2;
                }

                $ocupados[$clave] = $reserva->id;

                $filas[] = [
                    'reserva_id' => $reserva->id,
                    'espacio_id' => $reserva->espacio_id,
                    'fecha'      => $reserva->fecha->toDateString(),
                    'bloque'     => $bloque,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }

        if ($choques !== []) {
            throw new RuntimeException(
                "Hay reservas confirmadas que se traslapan entre sí y no se pueden representar\n"
                . "en bloques. Resuélvelas antes de migrar (cancela una de cada par):\n"
                . implode("\n", $choques)
            );
        }

        foreach (array_chunk($filas, 500) as $lote) {
            DB::table('bloques_reserva')->insert($lote);
        }

        if ($filas !== []) {
            echo '  bloques_reserva: ' . count($filas) . ' bloque(s) de '
                . $reservas->count() . ' reserva(s) confirmada(s)' . PHP_EOL;
        }
    }

    public function down(): void
    {
        DB::table('bloques_reserva')->truncate();
    }
};
