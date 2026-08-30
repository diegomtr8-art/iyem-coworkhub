<?php

namespace App\Console\Commands;

use App\Enums\BolsaDeHoras;
use App\Enums\MotivoMovimiento;
use App\Models\MovimientoHoras;
use App\Models\Suscripcion;
use App\Servicios\Horas\LibroDeHoras;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pasa los contadores que ya existían al libro, como saldo de arranque.
 *
 * Sin esto, la primera consulta al libro daría cero para todo el mundo y cada
 * miembro se encontraría con la bolsa llena: un regalo de horas justo el día
 * del despliegue.
 *
 * Es un comando y no una migración a propósito, y es **reversible**
 * (`--revertir`). Una migración a ciegas sobre saldos que ya están mal —y
 * llevan meses mal, por el BUG-01— es la peor forma de tocar esto: hay que
 * poder mirar el antes y el después, y volver atrás si no cuadra.
 *
 * Verifica por su cuenta: compara los saldos antes y después y aborta si no
 * coinciden.
 */
class SembrarLibroDeHoras extends Command
{
    protected $signature = 'nodico:sembrar-libro-horas
                            {--revertir : Borra los movimientos de saldo inicial.}
                            {--simular : No escribe nada; solo dice qué haría.}
                            {--solo-si-falta : Si ya está sembrado, sale con éxito en vez de con error. Para el despliegue.}';

    protected $description = 'Convierte los contadores de bolsas existentes en movimientos del libro.';

    public function handle(LibroDeHoras $libro): int
    {
        return $this->option('revertir')
            ? $this->revertir()
            : $this->sembrar($libro);
    }

    private function sembrar(LibroDeHoras $libro): int
    {
        $simular = (bool) $this->option('simular');

        $yaSembradas = MovimientoHoras::where('motivo', MotivoMovimiento::SaldoInicial->value)->count();

        if ($yaSembradas > 0 && ! $simular) {
            // En el despliegue esto no es un fallo: es que ya se hizo. Fuera
            // del despliegue sí lo es, porque significa que alguien está a
            // punto de duplicar el consumo de todo el mundo.
            if ($this->option('solo-si-falta')) {
                $this->info("El libro ya estaba sembrado ({$yaSembradas} movimiento(s)). No se toca nada.");

                return self::SUCCESS;
            }

            $this->error(
                "Ya hay {$yaSembradas} movimiento(s) de saldo inicial. Sembrar dos veces duplicaría "
                . 'el consumo. Usa --revertir primero si de verdad quieres rehacerlo.'
            );

            return self::FAILURE;
        }

        $antes    = $this->fotoDeSaldos();
        $sembrados = 0;

        foreach (Suscripcion::with('plan')->cursor() as $suscripcion) {
            foreach (BolsaDeHoras::cases() as $bolsa) {
                $consumo = $bolsa->consumo($suscripcion);

                if (abs($consumo) < 0.01) {
                    continue;
                }

                if ($simular) {
                    $this->line(sprintf(
                        '  [simulación] suscripción #%d · %s · %s consumido',
                        $suscripcion->id,
                        $bolsa->value,
                        $consumo,
                    ));
                    $sembrados++;

                    continue;
                }

                $libro->registrar(
                    suscripcion: $suscripcion,
                    bolsa: $bolsa,
                    cantidad: $consumo,
                    motivo: MotivoMovimiento::SaldoInicial,
                    nota: 'Consumo acumulado antes de que existiera el libro de horas.',
                    claveIdempotencia: "saldo_inicial:{$suscripcion->id}:{$bolsa->value}",
                );

                $sembrados++;
            }
        }

        if ($simular) {
            $this->info("Simulación: se escribirían {$sembrados} movimiento(s).");

            return self::SUCCESS;
        }

        $despues = $this->fotoDeSaldos();

        if ($antes !== $despues) {
            $this->error('Los saldos NO cuadran tras la siembra. Se revierte para no dejar el libro a medias.');

            foreach (array_keys($antes) as $clave) {
                if (($antes[$clave] ?? null) !== ($despues[$clave] ?? null)) {
                    $this->line("    {$clave}: antes {$antes[$clave]} · después " . ($despues[$clave] ?? 'ausente'));
                }
            }

            $this->revertir();

            return self::FAILURE;
        }

        $this->info("Sembrados {$sembrados} movimiento(s). Los saldos cuadran antes y después.");

        return self::SUCCESS;
    }

    private function revertir(): int
    {
        $borrados = MovimientoHoras::where('motivo', MotivoMovimiento::SaldoInicial->value)->delete();

        $this->info("Borrados {$borrados} movimiento(s) de saldo inicial.");
        $this->warn('Los contadores quedan como estaban: no los toca. Revísalos con nodico:reconstruir-saldos.');

        return self::SUCCESS;
    }

    /**
     * Consumo de cada bolsa de cada suscripción, tal y como lo ven los
     * contadores. Es la referencia contra la que se comprueba la siembra.
     *
     * @return array<string, float>
     */
    private function fotoDeSaldos(): array
    {
        $foto = [];

        foreach (DB::table('suscripciones')->orderBy('id')->get() as $fila) {
            foreach (BolsaDeHoras::cases() as $bolsa) {
                $foto["{$fila->id}:{$bolsa->value}"] = round((float) ($fila->{$bolsa->campoConsumo()} ?? 0), 2);
            }
        }

        return $foto;
    }
}
