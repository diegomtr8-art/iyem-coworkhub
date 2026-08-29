<?php

namespace App\Servicios\Reservas;

use App\Models\Espacio;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Qué está libre y qué no, en bloques de 30 minutos.
 *
 * Existe para que la pantalla de reservar **enseñe** la disponibilidad en vez de
 * que el miembro la descubra al enviar el formulario. Un formulario que acepta
 * cualquier hora y luego dice «ocupado» hace trabajar a la persona para
 * averiguar algo que el servidor ya sabía.
 *
 * Lo ocupado sale de `bloques_reserva`, que es la misma tabla que impide el
 * traslape: lo que se pinta y lo que se valida no pueden discrepar porque son
 * el mismo dato.
 */
class Disponibilidad
{
    public function __construct(
        private readonly ValidadorDeReserva $calendario,
        private readonly RegistroDeBloques $bloques,
    ) {
    }

    /**
     * Franja de un día para un espacio, bloque a bloque.
     *
     * @return array{
     *     fecha: string,
     *     abierto: bool,
     *     motivo_cierre: string|null,
     *     apertura: string|null,
     *     cierre: string|null,
     *     bloques: array<int, array{indice: int, hora: string, libre: bool, motivo: string|null}>
     * }
     */
    public function delDia(Espacio $espacio, CarbonImmutable $dia, ?CarbonImmutable $ahora = null): array
    {
        $ahora  = $ahora ?? CarbonImmutable::now();
        $franja = $this->calendario->franjaDelDia($espacio, $dia);

        if ($franja === null) {
            return [
                'fecha'         => $dia->toDateString(),
                'abierto'       => false,
                'motivo_cierre' => $this->motivoDelCierre($espacio, $dia),
                'apertura'      => null,
                'cierre'        => null,
                'bloques'       => [],
            ];
        }

        [$apertura, $cierre] = $franja;

        $granularidad = (int) config('nodico.operacion.granularidad_minutos', 30);
        $ocupados     = array_flip($this->bloques->ocupadosDe($espacio->id, $dia->toDateString()));
        $antelacion   = (int) config('nodico.operacion.antelacion_minima_minutos', 10);
        $limite       = $ahora->addMinutes($antelacion);

        $primero = intdiv($this->minutos($apertura), $granularidad);
        $ultimo  = intdiv($this->minutos($cierre) - 1, $granularidad);

        $bloques = [];

        for ($i = $primero; $i <= $ultimo; $i++) {
            $inicio = $dia->startOfDay()->addMinutes($i * $granularidad);
            $motivo = null;

            if (isset($ocupados[$i])) {
                $motivo = 'ocupado';
            } elseif ($inicio->lt($limite)) {
                // No es lo mismo «ya pasó» que «es demasiado pronto»: el
                // segundo tiene solución —pasar por recepción— y el primero no.
                $motivo = $inicio->lt($ahora) ? 'pasado' : 'demasiado_pronto';
            }

            $bloques[] = [
                'indice' => $i,
                'hora'   => $inicio->format('H:i'),
                'libre'  => $motivo === null,
                'motivo' => $motivo,
            ];
        }

        return [
            'fecha'         => $dia->toDateString(),
            'abierto'       => true,
            'motivo_cierre' => null,
            'apertura'      => $apertura,
            'cierre'        => $cierre,
            'bloques'       => $bloques,
        ];
    }

    /**
     * Resumen de varios días seguidos, para pintar el calendario de elección de
     * fecha con la disponibilidad ya visible.
     *
     * Devuelve solo el recuento de huecos por día: pintar los 20 bloques de cada
     * uno de los 90 días sería mandar al navegador 1.800 objetos para enseñar
     * un calendario de casillas.
     *
     * @return array<int, array{fecha: string, abierto: bool, libres: int, total: int, motivo_cierre: string|null}>
     */
    public function delPeriodo(
        Espacio $espacio,
        CarbonImmutable $desde,
        CarbonImmutable $hasta,
        ?CarbonImmutable $ahora = null,
    ): array {
        $ahora = $ahora ?? CarbonImmutable::now();

        // Una sola consulta para todo el periodo, en vez de una por día.
        $ocupadosPorDia = DB::table('bloques_reserva')
            ->where('espacio_id', $espacio->id)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->get()
            ->groupBy(fn ($fila) => substr((string) $fila->fecha, 0, 10))
            ->map(fn ($filas) => array_flip($filas->pluck('bloque')->map(fn ($b) => (int) $b)->all()));

        $granularidad = (int) config('nodico.operacion.granularidad_minutos', 30);
        $limite       = $ahora->addMinutes((int) config('nodico.operacion.antelacion_minima_minutos', 10));

        $dias = [];

        for ($dia = $desde; $dia->lte($hasta); $dia = $dia->addDay()) {
            $franja = $this->calendario->franjaDelDia($espacio, $dia);

            if ($franja === null) {
                $dias[] = [
                    'fecha'         => $dia->toDateString(),
                    'abierto'       => false,
                    'libres'        => 0,
                    'total'         => 0,
                    'motivo_cierre' => $this->motivoDelCierre($espacio, $dia),
                ];

                continue;
            }

            [$apertura, $cierre] = $franja;

            $ocupados = $ocupadosPorDia[$dia->toDateString()] ?? [];
            $primero  = intdiv($this->minutos($apertura), $granularidad);
            $ultimo   = intdiv($this->minutos($cierre) - 1, $granularidad);

            $libres = 0;

            for ($i = $primero; $i <= $ultimo; $i++) {
                if (isset($ocupados[$i])) {
                    continue;
                }

                if ($dia->startOfDay()->addMinutes($i * $granularidad)->lt($limite)) {
                    continue;
                }

                $libres++;
            }

            $dias[] = [
                'fecha'         => $dia->toDateString(),
                'abierto'       => true,
                'libres'        => $libres,
                'total'         => $ultimo - $primero + 1,
                'motivo_cierre' => null,
            ];
        }

        return $dias;
    }

    /** Por qué está cerrado: el miembro merece saber si es festivo o es domingo. */
    private function motivoDelCierre(Espacio $espacio, CarbonImmutable $dia): string
    {
        $festivo = \App\Models\DiaFestivo::enLaFecha($dia);

        if ($festivo && $festivo->cerrado_todo_el_dia) {
            return $festivo->nombre;
        }

        if (! in_array($dia->dayOfWeekIso, $this->calendario->diasHabiles($espacio), true)) {
            return 'Cerrado los ' . $dia->translatedFormat('l') . 's';
        }

        return 'Cerrado';
    }

    private function minutos(string $hora): int
    {
        return \App\Models\Reserva::aMinutos($hora);
    }
}
