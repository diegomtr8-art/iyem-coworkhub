<?php

namespace App\Console\Commands;

use App\Enums\MotivoMovimiento;
use App\Models\Reserva;
use App\Servicios\Horas\LibroDeHoras;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Fase 1.6 — no-show.
 *
 * El estatus `No_Show` existía en el enum de `reservas` desde el principio y
 * **nada en el sistema lo asignaba**. Sin esto, no presentarse sale gratis: las
 * horas se quedan consumidas igual que en una cancelación tardía, sí, pero el
 * operativo no tiene forma de distinguir a quien avisó tarde de quien no vino,
 * y la tasa de no-show del informe de la Fase 3.10 sería siempre cero.
 *
 * **No devuelve horas**: es lo que le da sentido a la regla de las 2 horas de
 * antelación. Se limita a poner el estatus y a dejar constancia en el libro con
 * un movimiento de cantidad 0.
 */
class MarcarNoShow extends Command
{
    protected $signature = 'nodico:marcar-no-show
                            {--margen=15 : Minutos de cortesía tras el fin de la reserva.}
                            {--simular : No escribe nada; solo dice qué haría.}';

    protected $description = 'Marca como No_Show las reservas que pasaron sin check-in.';

    public function handle(LibroDeHoras $libro): int
    {
        $margen  = max(0, (int) $this->option('margen'));
        $simular = (bool) $this->option('simular');
        $ahora   = CarbonImmutable::now();

        $candidatas = Reserva::with(['espacio', 'suscripcion.plan', 'user'])
            ->confirmadas()
            ->whereDate('fecha', '<=', $ahora->toDateString())
            ->doesntHave('checkin')
            ->get()
            // El filtro fino en PHP: la hora vive en su propia columna y
            // comparar `fecha + hora` en SQL portable entre MySQL y SQLite no
            // vale la pena para el volumen que maneja Nódico.
            ->filter(fn (Reserva $reserva) => $reserva->finEnCalendario()->addMinutes($margen)->lt($ahora));

        if ($candidatas->isEmpty()) {
            $this->info('No hay reservas por marcar.');

            return self::SUCCESS;
        }

        $marcadas = 0;

        foreach ($candidatas as $reserva) {
            $etiqueta = sprintf(
                '  %s · %s · %s %s–%s',
                $reserva->user?->name ?? "reserva {$reserva->id}",
                $reserva->espacio?->nombre ?? 'espacio borrado',
                $reserva->fecha->toDateString(),
                substr($reserva->hora_inicio, 0, 5),
                substr($reserva->hora_fin, 0, 5),
            );

            if ($simular) {
                $this->line('  [simulación]' . $etiqueta);

                continue;
            }

            $reserva->update(['estatus' => 'No_Show']);

            // Cantidad 0: las horas ya se consumieron al reservar y **no se
            // devuelven**. Esto solo deja el porqué en el libro, para que la
            // ficha del miembro pueda explicar el saldo.
            if ($reserva->suscripcion && $reserva->bolsa()) {
                $libro->registrar(
                    suscripcion: $reserva->suscripcion,
                    bolsa: $reserva->bolsa(),
                    cantidad: 0,
                    motivo: MotivoMovimiento::NoShow,
                    reserva: $reserva,
                    nota: 'No hubo check-in. Las ' . $reserva->duracionEnHoras()
                        . ' h se consumen igual.',
                    claveIdempotencia: "no_show:{$reserva->id}",
                );
            }

            $marcadas++;
            $this->line($etiqueta);
        }

        $this->info($simular
            ? 'Simulación: ' . $candidatas->count() . ' reserva(s) se marcarían.'
            : "Marcadas {$marcadas} reserva(s) como no-show.");

        return self::SUCCESS;
    }
}
