<?php

namespace App\Console\Commands;

use App\Models\Suscripcion;
use App\Servicios\Horas\LibroDeHoras;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Fase 1.5 — reinicio de bolsas por aniversario.
 *
 * Corre a diario y abre el ciclo de las suscripciones que cumplen aniversario.
 *
 * **Es idempotente**, y no por convención sino por el índice único de
 * `movimientos_horas.clave_idempotencia`: correrlo dos veces el mismo día no
 * puede reiniciar dos veces ni aunque dos procesos entren a la vez.
 *
 * Y deja rastro. Si el servidor estuvo caído un día —o tres— se recupera con
 * `--desde=2026-08-25`, que repasa cada día perdido en orden sin duplicar
 * ninguno. Un comando programado que no se puede rehacer obliga a reparar los
 * saldos a mano, y eso es exactamente lo que este trabajo vino a quitar.
 */
class ReiniciarCiclos extends Command
{
    protected $signature = 'nodico:reiniciar-ciclos
                            {--desde= : Primer día a repasar (Y-m-d). Por omisión, hoy.}
                            {--hasta= : Último día a repasar (Y-m-d). Por omisión, hoy.}
                            {--simular : No escribe nada; solo dice qué haría.}';

    protected $description = 'Abre el ciclo de las suscripciones que cumplen aniversario hoy.';

    public function handle(LibroDeHoras $libro): int
    {
        $desde = $this->option('desde')
            ? CarbonImmutable::parse($this->option('desde'))->startOfDay()
            : CarbonImmutable::today();

        $hasta = $this->option('hasta')
            ? CarbonImmutable::parse($this->option('hasta'))->startOfDay()
            : CarbonImmutable::today();

        if ($desde->gt($hasta)) {
            $this->error('El --desde es posterior al --hasta.');

            return self::FAILURE;
        }

        $simular  = (bool) $this->option('simular');
        $abiertos = 0;
        $vistos   = 0;

        for ($dia = $desde; $dia->lte($hasta); $dia = $dia->addDay()) {
            $suscripciones = Suscripcion::with('plan')
                ->where('estatus', 'Activa')
                ->whereDate('fecha_inicio', '<=', $dia->toDateString())
                ->whereDate('fecha_fin', '>=', $dia->toDateString())
                ->cursor();

            foreach ($suscripciones as $suscripcion) {
                if (! ($suscripcion->plan?->tieneCiclosMensuales() ?? false)) {
                    continue;
                }

                // Solo cuando el día ES el aniversario. El resto de días el
                // ciclo ya está abierto y no hay nada que hacer.
                if (! $suscripcion->cicloInicio($dia)->isSameDay($dia)) {
                    continue;
                }

                $vistos++;

                if ($simular) {
                    $this->line(sprintf(
                        '  [simulación] %s · %s · ciclo del %s',
                        $suscripcion->user?->name ?? "suscripción {$suscripcion->id}",
                        $suscripcion->plan->nombre,
                        $dia->toDateString(),
                    ));

                    continue;
                }

                if ($libro->abrirCiclo($suscripcion, $dia)) {
                    $abiertos++;

                    $this->line(sprintf(
                        '  %s · %s · bolsas reiniciadas para el ciclo del %s',
                        $suscripcion->user?->name ?? "suscripción {$suscripcion->id}",
                        $suscripcion->plan->nombre,
                        $dia->toDateString(),
                    ));
                }
            }
        }

        if ($simular) {
            $this->info("Simulación: {$vistos} suscripción(es) cumplirían ciclo.");

            return self::SUCCESS;
        }

        $yaEstaban = $vistos - $abiertos;

        $this->info("Ciclos abiertos: {$abiertos}." . ($yaEstaban > 0
            ? " {$yaEstaban} ya estaba(n) abierto(s); no se duplicó ninguno."
            : ''));

        return self::SUCCESS;
    }
}
