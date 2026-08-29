<?php

namespace App\Servicios\Reservas;

use App\Models\Reserva;
use Illuminate\Support\Facades\DB;

/**
 * Materializa el rango horario de una reserva en bloques de 30 minutos, para
 * que el traslape lo impida la **base de datos** y no solo el controlador
 * (BUG-03).
 *
 * Ver la migración `create_bloques_reserva_table` para el porqué de la forma.
 */
class RegistroDeBloques
{
    /**
     * Escribe los bloques de una reserva. Lanza `QueryException` si alguno ya
     * está ocupado: **ese fallo es la garantía**, no un accidente a capturar
     * a la ligera.
     */
    public function ocupar(Reserva $reserva): void
    {
        $ahora = now();

        $filas = array_map(fn (int $bloque) => [
            'reserva_id' => $reserva->id,
            'espacio_id' => $reserva->espacio_id,
            'fecha'      => $reserva->fecha->toDateString(),
            'bloque'     => $bloque,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ], $this->bloquesDe($reserva->hora_inicio, $reserva->hora_fin));

        DB::table('bloques_reserva')->insert($filas);
    }

    /** Libera los bloques de una reserva cancelada, para que su horario vuelva a estar disponible. */
    public function liberar(Reserva $reserva): void
    {
        DB::table('bloques_reserva')->where('reserva_id', $reserva->id)->delete();
    }

    /**
     * Índices de bloque que cubre un rango. El fin es **exclusivo**: una reserva
     * de 09:00 a 10:00 ocupa los bloques 18 y 19 (09:00 y 09:30), no el 20, de
     * modo que otra de 10:00 a 11:00 encaja a continuación sin chocar.
     *
     * @return array<int, int>
     */
    public function bloquesDe(string $horaInicio, string $horaFin): array
    {
        $granularidad = (int) config('nodico.operacion.granularidad_minutos', 30);

        $inicio = intdiv(Reserva::aMinutos($horaInicio), $granularidad);
        $fin    = intdiv(Reserva::aMinutos($horaFin) - 1, $granularidad);

        return range($inicio, $fin);
    }

    /**
     * Bloques ya ocupados de un espacio en una fecha. Lo consume la pantalla de
     * reservar para pintar la franja del día con lo ocupado ya marcado, en vez
     * de que el miembro lo descubra al enviar.
     *
     * @return array<int, int>
     */
    public function ocupadosDe(int $espacioId, string $fecha): array
    {
        return DB::table('bloques_reserva')
            ->where('espacio_id', $espacioId)
            ->where('fecha', $fecha)
            ->pluck('bloque')
            ->map(fn ($b) => (int) $b)
            ->all();
    }
}
