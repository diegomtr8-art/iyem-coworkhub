<?php

namespace App\Console\Commands;

use App\Servicios\Emprendedores\RotacionDeEmprendedor;
use Illuminate\Console\Command;

/**
 * Fase 4.H — pasa al siguiente «emprendedor de la semana». Corre por cron cada
 * lunes (ver routes/console.php). Idempotente en la práctica: si ya se rotó esta
 * semana, el que quede seguirá siendo el más reciente y volver a correrlo solo
 * avanza la cola una posición —por eso el cron lo dispara una vez por semana—.
 */
class RotarEmprendedor extends Command
{
    protected $signature = 'nodico:rotar-emprendedor {--simular : No cambia nada, solo dice quién saldría}';

    protected $description = 'Rota al siguiente emprendedor de la semana (rotación justa).';

    public function handle(RotacionDeEmprendedor $rotacion): int
    {
        if ($this->option('simular')) {
            $plan = $rotacion->calendario(1);
            $this->info($plan === []
                ? 'No hay emprendedores elegibles.'
                : 'Saldría: ' . $plan[0]['nombre']);

            return self::SUCCESS;
        }

        $r = $rotacion->rotar();

        $this->info(match ($r['estado']) {
            'rotado'        => 'Emprendedor de la semana: ' . $r['destacado'],
            'fijado'        => 'Se respeta el fijado a mano: ' . $r['destacado'],
            'sin_elegibles' => $r['aviso'],
            default         => 'Rotación ejecutada.',
        });

        return self::SUCCESS;
    }
}
