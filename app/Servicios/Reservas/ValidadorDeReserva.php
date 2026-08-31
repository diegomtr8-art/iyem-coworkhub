<?php

namespace App\Servicios\Reservas;

use App\Models\DiaFestivo;
use App\Models\Espacio;
use App\Models\Reserva;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Las reglas de cuándo se puede reservar (Fase 1.4).
 *
 * **Un solo validador para el portal y para el panel operativo.** La misma
 * regla escrita en dos sitios es como nació el BUG-02, y aquí habría cuatro
 * copias: la vista que pinta las horas, el controlador del miembro, el del
 * operativo y la agenda de arrastrar y soltar.
 *
 * Lo que comprueba:
 *
 * - que el día sea hábil y no festivo;
 * - que la reserva quepa dentro del horario, sin cruzar el cierre;
 * - que empiece y acabe en un bloque de 30 minutos y dure al menos una hora;
 * - que no sea ni demasiado pronto (10 min) ni demasiado tarde (3 meses).
 *
 * El cupo de horas **no** está aquí: eso es el libro. Esto es el calendario.
 */
class ValidadorDeReserva
{
    /**
     * Valida un intento de reserva y lanza `ValidationException` con el campo y
     * el mensaje exactos si algo no encaja.
     *
     * @param  bool  $comoOperativo  Recepción puede saltarse la antelación
     *         mínima —alguien está enfrente pidiendo la sala para ahora mismo—
     *         pero no el horario ni los festivos: el local está cerrado igual.
     */
    public function validar(
        Espacio $espacio,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        bool $comoOperativo = false,
        ?CarbonImmutable $ahora = null,
    ): void {
        $ahora = $ahora ?? CarbonImmutable::now();
        $dia   = CarbonImmutable::parse($fecha)->startOfDay();

        $this->validarGranularidad($horaInicio, $horaFin);
        $this->validarDuracion($horaInicio, $horaFin);
        $this->validarAntelacion($dia, $horaInicio, $ahora, $comoOperativo);
        $this->validarCalendario($espacio, $dia);
        $this->validarHorario($espacio, $dia, $horaInicio, $horaFin);
    }

    /** Si el espacio abre ese día, sin mirar horas. */
    public function abreEse(Espacio $espacio, CarbonImmutable $dia): bool
    {
        if (! in_array($dia->dayOfWeekIso, $this->diasHabiles($espacio), true)) {
            return false;
        }

        $festivo = DiaFestivo::enLaFecha($dia);

        return ! ($festivo && $festivo->cerrado_todo_el_dia);
    }

    /**
     * Franja de apertura de un espacio en un día, ya resueltos el horario
     * propio y los cierres parciales por festivo.
     *
     * @return array{0: string, 1: string}|null `null` si ese día está cerrado
     */
    public function franjaDelDia(Espacio $espacio, CarbonImmutable $dia): ?array
    {
        if (! $this->abreEse($espacio, $dia)) {
            return null;
        }

        $apertura = substr((string) ($espacio->hora_apertura ?? config('nodico.operacion.apertura')), 0, 5);
        $cierre   = substr((string) ($espacio->hora_cierre ?? config('nodico.operacion.cierre')), 0, 5);

        // Un festivo de cierre parcial recorta la franja, nunca la amplía: si
        // el 24 de diciembre se cierra a las 14:00, no puede abrirse antes de
        // lo que abre un día normal.
        $festivo = DiaFestivo::enLaFecha($dia);

        if ($festivo && ! $festivo->cerrado_todo_el_dia) {
            if ($festivo->hora_apertura) {
                $apertura = max($apertura, substr((string) $festivo->hora_apertura, 0, 5));
            }

            if ($festivo->hora_cierre) {
                $cierre = min($cierre, substr((string) $festivo->hora_cierre, 0, 5));
            }
        }

        return Reserva::aMinutos($apertura) < Reserva::aMinutos($cierre)
            ? [$apertura, $cierre]
            : null;
    }

    /** Días de la semana en que abre el espacio, en formato ISO (1 = lunes). */
    public function diasHabiles(Espacio $espacio): array
    {
        $propios = $espacio->dias_operacion;

        return is_array($propios) && $propios !== []
            ? array_map('intval', $propios)
            : array_map('intval', config('nodico.operacion.dias_habiles', [1, 2, 3, 4, 5]));
    }

    // ── Reglas ──────────────────────────────────────────────────────────────

    private function validarGranularidad(string $horaInicio, string $horaFin): void
    {
        $granularidad = (int) config('nodico.operacion.granularidad_minutos', 30);

        foreach (['hora_inicio' => $horaInicio, 'hora_fin' => $horaFin] as $campo => $hora) {
            if (Reserva::aMinutos($hora) % $granularidad !== 0) {
                throw ValidationException::withMessages([
                    $campo => "Las reservas van en bloques de una hora: elige una hora en punto.",
                ]);
            }
        }
    }

    private function validarDuracion(string $horaInicio, string $horaFin): void
    {
        $minima = (float) config('nodico.operacion.duracion_minima_horas', 1);
        $horas  = Reserva::calcularHoras($horaInicio, $horaFin);

        if ($horas < $minima) {
            throw ValidationException::withMessages([
                'hora_fin' => "La reserva mínima es de {$minima} hora(s) y estás pidiendo {$horas}.",
            ]);
        }
    }

    private function validarAntelacion(
        CarbonImmutable $dia,
        string $horaInicio,
        CarbonImmutable $ahora,
        bool $comoOperativo,
    ): void {
        $inicio = $dia->setTimeFromTimeString(substr($horaInicio, 0, 5));

        if (! $comoOperativo) {
            $minima = (int) config('nodico.operacion.antelacion_minima_minutos', 10);

            if ($inicio->lt($ahora->addMinutes($minima))) {
                throw ValidationException::withMessages([
                    'hora_inicio' => $inicio->lt($ahora)
                        ? 'Esa hora ya pasó.'
                        : "Las reservas se hacen con al menos {$minima} minutos de antelación. "
                            . 'Si necesitas la sala ahora mismo, pásate por recepción.',
                ]);
            }
        }

        $maxima = (int) config('nodico.operacion.antelacion_maxima_dias', 90);

        if ($dia->gt($ahora->addDays($maxima)->startOfDay())) {
            throw ValidationException::withMessages([
                'fecha' => 'Solo se puede reservar con ' . round($maxima / 30) . ' meses de antelación como máximo.',
            ]);
        }
    }

    private function validarCalendario(Espacio $espacio, CarbonImmutable $dia): void
    {
        if (! in_array($dia->dayOfWeekIso, $this->diasHabiles($espacio), true)) {
            throw ValidationException::withMessages([
                'fecha' => 'Nódico abre de lunes a viernes. El ' . $dia->translatedFormat('l j \d\e F')
                    . ' está cerrado.',
            ]);
        }

        $festivo = DiaFestivo::enLaFecha($dia);

        if ($festivo && $festivo->cerrado_todo_el_dia) {
            throw ValidationException::withMessages([
                'fecha' => "El {$dia->translatedFormat('j \d\e F')} está cerrado: {$festivo->nombre}.",
            ]);
        }
    }

    private function validarHorario(
        Espacio $espacio,
        CarbonImmutable $dia,
        string $horaInicio,
        string $horaFin,
    ): void {
        $franja = $this->franjaDelDia($espacio, $dia);

        if ($franja === null) {
            throw ValidationException::withMessages([
                'fecha' => 'Ese día el espacio no abre.',
            ]);
        }

        [$apertura, $cierre] = $franja;

        if (Reserva::aMinutos($horaInicio) < Reserva::aMinutos($apertura)) {
            throw ValidationException::withMessages([
                'hora_inicio' => "{$espacio->nombre} abre a las {$apertura}.",
            ]);
        }

        // El fin puede coincidir con el cierre, pero no pasarlo: una reserva
        // que cruza el cierre deja a alguien dentro con el local cerrado.
        if (Reserva::aMinutos($horaFin) > Reserva::aMinutos($cierre)) {
            throw ValidationException::withMessages([
                'hora_fin' => "{$espacio->nombre} cierra a las {$cierre} y tu reserva termina a las "
                    . substr($horaFin, 0, 5) . '.',
            ]);
        }
    }
}
