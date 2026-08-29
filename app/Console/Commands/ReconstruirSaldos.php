<?php

namespace App\Console\Commands;

use App\Models\Suscripcion;
use App\Servicios\Horas\LibroDeHoras;
use Illuminate\Console\Command;

/**
 * Rehace los contadores de `suscripciones` desde el libro de movimientos.
 *
 * Los contadores son caché. Esto es lo que hace que serlo signifique algo: si
 * divergen del libro, se tiran y se recalculan. Sin `--arreglar` solo informa,
 * que es como conviene correrlo en producción de forma periódica.
 *
 * Es también la prueba de consistencia de la Fase 4 en forma de comando: si
 * algún día vuelve a colarse una escritura directa a un contador, esto lo
 * detecta en la siguiente pasada.
 */
class ReconstruirSaldos extends Command
{
    protected $signature = 'nodico:reconstruir-saldos
                            {--arreglar : Escribe los contadores. Sin esto solo informa.}
                            {--suscripcion= : Limita a una suscripción por id.}';

    protected $description = 'Compara los contadores de bolsas contra el libro de horas y los rehace.';

    public function handle(LibroDeHoras $libro): int
    {
        $arreglar = (bool) $this->option('arreglar');

        $consulta = Suscripcion::with('plan')
            ->when($this->option('suscripcion'), fn ($q, $id) => $q->whereKey($id));

        $revisadas   = 0;
        $divergentes = 0;

        foreach ($consulta->cursor() as $suscripcion) {
            $revisadas++;

            $problemas = $libro->verificarConsistencia($suscripcion);

            if ($problemas === []) {
                continue;
            }

            $divergentes++;

            $this->warn(sprintf(
                'Suscripción #%d (%s):',
                $suscripcion->id,
                $suscripcion->user?->name ?? 'sin usuario',
            ));

            foreach ($problemas as $bolsa => $valores) {
                $this->line(sprintf(
                    '    %-10s caché %s vs libro %s',
                    $bolsa,
                    $valores['cache'],
                    $valores['libro'],
                ));
            }

            if ($arreglar) {
                $libro->reconstruirCache($suscripcion);
                $this->line('    → contadores rehechos desde el libro.');
            }
        }

        if ($divergentes === 0) {
            $this->info("Revisadas {$revisadas} suscripción(es). Los contadores cuadran con el libro.");

            return self::SUCCESS;
        }

        if ($arreglar) {
            $this->info("Revisadas {$revisadas}. Rehechas {$divergentes}.");

            return self::SUCCESS;
        }

        $this->error(
            "Revisadas {$revisadas}. {$divergentes} divergen del libro. "
            . 'Vuelve a correrlo con --arreglar para rehacerlas.'
        );

        // Código distinto de cero a propósito: así un cron o un CI se entera.
        return self::FAILURE;
    }
}
