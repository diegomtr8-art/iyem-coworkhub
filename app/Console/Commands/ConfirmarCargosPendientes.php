<?php

namespace App\Console\Commands;

use App\Models\CargoPasarela;
use App\Servicios\Pagos\Openpay\ClienteOpenpay;
use App\Servicios\Pagos\Openpay\ConfirmadorDeCargo;
use App\Servicios\Pagos\Openpay\ErrorDeOpenpay;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Consulta en la pasarela (Openpay o Ecommerce BBVA) los cargos que siguen
 * esperando al banco.
 *
 * **No es opcional.** Si la persona paga y cierra la pestaña antes de volver a
 * Nódico, nadie más se entera del pago: Ecommerce BBVA no manda webhooks, y en
 * Openpay el webhook avisa pero no es la única red. Este proceso lo encuentra
 * y activa la membresía por la misma puerta que la página de regreso
 * (`ConfirmadorDeCargo`).
 *
 * Cada cargo se consulta con las llaves de SU plataforma, aunque la pasarela
 * activa haya cambiado después. Un cargo que sigue sin terminarse después de
 * `pagos.horas_de_espera` se da por abandonado (se abandonó el 3-D Secure) y
 * se deja de consultar; en la pasarela no se toca.
 */
class ConfirmarCargosPendientes extends Command
{
    protected $signature = 'nodico:confirmar-cargos';

    protected $description = 'Consulta en la pasarela los cargos con tarjeta pendientes y activa los que ya se pagaron.';

    private const PLATAFORMAS = ['openpay', 'bbva'];

    public function handle(ConfirmadorDeCargo $confirmador): int
    {
        $limite = now()->subHours((int) config('pagos.horas_de_espera', 24));

        $abandonados = CargoPasarela::whereIn('pasarela', self::PLATAFORMAS)
            ->whereIn('estado', [CargoPasarela::PENDIENTE, CargoPasarela::CREANDO])
            ->where('created_at', '<', $limite)
            ->update(['estado' => CargoPasarela::ABANDONADO]);

        if ($abandonados) {
            Log::info("Pasarela: {$abandonados} cargo(s) dados por abandonados.");
        }

        $consultados = 0;
        $fallas = 0;

        foreach (self::PLATAFORMAS as $plataforma) {
            $pendientes = CargoPasarela::where('pasarela', $plataforma)
                ->esperandoAlBanco()
                ->where('created_at', '>=', $limite)
                ->orderBy('id');

            if (! $pendientes->exists()) {
                continue;
            }

            if (! ClienteOpenpay::para($plataforma)->configurado()) {
                $this->warn("Hay cargos de {$plataforma} pendientes, pero sus llaves ya no están configuradas.");
                Log::warning("Pasarela: cargos de {$plataforma} pendientes sin llaves para consultarlos.");

                continue;
            }

            $pendientes->each(function (CargoPasarela $cargo) use ($confirmador, &$consultados, &$fallas) {
                try {
                    $confirmador->confirmar($cargo);
                    $consultados++;
                } catch (ErrorDeOpenpay $e) {
                    $fallas++;
                    Log::warning('Pasarela: no se pudo consultar un cargo pendiente.', ['cargo' => $cargo->id, ...$e->contexto()]);
                }
            });
        }

        $this->info("Cargos consultados: {$consultados}. Fallas de consulta: {$fallas}. Abandonados: {$abandonados}.");

        return self::SUCCESS;
    }
}
