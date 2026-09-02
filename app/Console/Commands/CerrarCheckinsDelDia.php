<?php

namespace App\Console\Commands;

use App\Models\Checkin;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Cierra los check-ins que quedaron abiertos al terminar la jornada.
 *
 * Sin esto, quien entra y nunca marca salida deja su check-in vivo para siempre,
 * y el % de ocupación del tablero miente el resto del día siguiente. Corre al
 * cierre: pone la hora de salida en el cierre (o ahora, lo que sea menor) y deja
 * la duración calculada. Lo abierto de días anteriores se cierra igual.
 */
class CerrarCheckinsDelDia extends Command
{
    protected $signature = 'nodico:cerrar-checkins
                            {--simular : No escribe nada; solo dice cuántos cerraría.}';

    protected $description = 'Cierra los check-ins que quedaron abiertos al cierre de la jornada.';

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');
        $ahora   = CarbonImmutable::now();
        $cierre  = substr((string) config('nodico.operacion.cierre', '19:00'), 0, 5);

        $abiertos = Checkin::whereNull('hora_salida')->get();

        if ($abiertos->isEmpty()) {
            $this->info('No hay check-ins abiertos.');

            return self::SUCCESS;
        }

        $cerrados = 0;
        foreach ($abiertos as $c) {
            if (! $c->hora_entrada) {
                continue;
            }
            // Cierre del día en que entró; nunca antes de su propia entrada.
            $finDelDia = CarbonImmutable::parse($c->hora_entrada->toDateString() . ' ' . $cierre);
            $salida = $finDelDia->lt($ahora) ? $finDelDia : $ahora;
            if ($salida->lt($c->hora_entrada)) {
                $salida = $c->hora_entrada;
            }

            if (! $simular) {
                $c->update([
                    'hora_salida'      => $salida,
                    'duracion_minutos' => (int) $c->hora_entrada->diffInMinutes($salida),
                ]);
            }
            $cerrados++;
        }

        $this->info(($simular ? '[simulación] ' : '') . "Check-ins cerrados: {$cerrados}.");

        return self::SUCCESS;
    }
}
