<?php

namespace App\Console\Commands;

use App\Models\CargoPasarela;
use App\Models\SuscripcionPasarela;
use App\Servicios\Pagos\Openpay\ClienteOpenpay;
use App\Servicios\Pagos\Openpay\ErrorDeOpenpay;
use App\Servicios\Pagos\Openpay\SuscripcionesOpenpay;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pone al día las suscripciones de Openpay con lo que diga Openpay.
 *
 * **No es opcional.** Es lo que renueva la membresía cuando Openpay cobra un
 * periodo, la suspende cuando el cobro se rechaza y deja de renovarla cuando
 * Openpay la cancela sola al agotar los reintentos, que no tiene aviso propio.
 * Los webhooks (paso 4) hacen lo mismo al momento; esto es la red.
 *
 * También reintenta el alta de las suscripciones cuyo primer cobro se
 * confirmó pero Openpay no respondió al darlas de alta.
 */
class SincronizarSuscripciones extends Command
{
    protected $signature = 'nodico:sincronizar-suscripciones';

    protected $description = 'Consulta en Openpay las suscripciones y renueva, suspende o cierra las membresías.';

    public function handle(SuscripcionesOpenpay $suscripciones): int
    {
        $plataformas = ClienteOpenpay::conSuscripcionesConfiguradas();

        if ($plataformas === []) {
            $this->line('Ninguna pasarela con suscripciones está configurada: nada que sincronizar.');

            return self::SUCCESS;
        }

        $altas = 0;
        CargoPasarela::whereIn('pasarela', $plataformas)
            ->where('suscribir', true)
            ->where('estado', CargoPasarela::COMPLETADO)
            ->where('confirmado_en', '>=', now()->subDays(7))
            ->whereNotExists(fn ($q) => $q->from('suscripciones_pasarela')->whereColumn('suscripciones_pasarela.cargo_id', 'cargos_pasarela.id'))
            ->each(function (CargoPasarela $cargo) use ($suscripciones, &$altas) {
                try {
                    if ($suscripciones->asegurarAlta($cargo)) {
                        $altas++;
                    }
                } catch (ErrorDeOpenpay $e) {
                    Log::warning('Openpay: alta de suscripción pendiente, se reintentará.', ['cargo' => $cargo->id, ...$e->contexto()]);
                }
            });

        $revisadas = 0;
        $fallas = 0;
        SuscripcionPasarela::whereIn('pasarela', $plataformas)
            ->where(fn ($q) => $q->vivas()->orWhere('estado', 'unpaid'))
            ->orderBy('id')
            ->each(function (SuscripcionPasarela $suscripcion) use ($suscripciones, &$revisadas, &$fallas) {
                try {
                    $suscripciones->sincronizar($suscripcion);
                    $revisadas++;
                } catch (ErrorDeOpenpay $e) {
                    $fallas++;
                    Log::warning('Openpay: no se pudo sincronizar una suscripción.', ['suscripcion' => $suscripcion->suscripcion_id, ...$e->contexto()]);
                }
            });

        $this->info("Altas pendientes resueltas: {$altas}. Suscripciones revisadas: {$revisadas}. Fallas: {$fallas}.");

        return self::SUCCESS;
    }
}
