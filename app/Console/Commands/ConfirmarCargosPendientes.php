<?php

namespace App\Console\Commands;

use App\Models\CargoPasarela;
use App\Servicios\Pagos\Bbva\ClienteBbva;
use App\Servicios\Pagos\Bbva\ConfirmadorDeCargo;
use App\Servicios\Pagos\Bbva\ErrorDeBbva;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Consulta en BBVA los cargos que siguen esperando al banco.
 *
 * **No es opcional.** Ecommerce BBVA no manda webhooks: si la persona paga y
 * cierra la pestaña antes de volver a Nódico, nadie más se entera del pago.
 * Este proceso lo encuentra y activa la membresía (por la misma puerta que la
 * página de regreso: `ConfirmadorDeCargo`).
 *
 * Un cargo que sigue sin terminarse después de `pagos.bbva.horas_de_espera` se
 * da por abandonado (se abandonó el 3-D Secure) y se deja de consultar. El
 * cargo en BBVA no se toca.
 */
class ConfirmarCargosPendientes extends Command
{
    protected $signature = 'nodico:confirmar-cargos';

    protected $description = 'Consulta en BBVA los cargos con tarjeta pendientes y activa los que ya se pagaron.';

    public function handle(ClienteBbva $bbva, ConfirmadorDeCargo $confirmador): int
    {
        $limite = now()->subHours((int) config('pagos.bbva.horas_de_espera', 24));

        $abandonados = CargoPasarela::where('pasarela', 'bbva')
            ->whereIn('estado', [CargoPasarela::PENDIENTE, CargoPasarela::CREANDO])
            ->where('created_at', '<', $limite)
            ->update(['estado' => CargoPasarela::ABANDONADO]);

        if ($abandonados) {
            Log::info("BBVA: {$abandonados} cargo(s) dados por abandonados.");
        }

        if (! $bbva->configurado()) {
            $this->line('BBVA no está configurado: no hay cargos que consultar.');

            return self::SUCCESS;
        }

        $consultados = 0;
        $fallas = 0;

        CargoPasarela::where('pasarela', 'bbva')
            ->esperandoAlBanco()
            ->where('created_at', '>=', $limite)
            ->orderBy('id')
            ->each(function (CargoPasarela $cargo) use ($confirmador, &$consultados, &$fallas) {
                try {
                    $confirmador->confirmar($cargo);
                    $consultados++;
                } catch (ErrorDeBbva $e) {
                    $fallas++;
                    Log::warning('BBVA: no se pudo consultar un cargo pendiente.', ['cargo' => $cargo->id, ...$e->contexto()]);
                }
            });

        $this->info("Cargos consultados: {$consultados}. Fallas de consulta: {$fallas}. Abandonados: {$abandonados}.");

        return self::SUCCESS;
    }
}
