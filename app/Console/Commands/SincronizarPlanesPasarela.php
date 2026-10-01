<?php

namespace App\Console\Commands;

use App\Servicios\Pagos\Openpay\ClienteOpenpay;
use App\Servicios\Pagos\Openpay\ErrorDeOpenpay;
use App\Servicios\Pagos\Openpay\SuscripcionesOpenpay;
use Illuminate\Console\Command;

/**
 * Crea en Openpay los planes de los que se cobran solos cada mes (Nodo Pro,
 * Nodo Match) y guarda a cuál corresponde cada uno (`planes_pasarela`).
 *
 * Es la versión Openpay de `nodico:stripe-precios`, que sigue existiendo
 * mientras Stripe conviva. Idempotente: un plan ya sincronizado a su precio
 * actual se salta. Si el precio cambió, se crea un plan nuevo (Openpay no deja
 * editar el importe) y el anterior queda inactivo; las suscripciones que ya
 * existen siguen con su precio.
 *
 * El importe va en PESOS (`Importe::enPesos`), nunca en centavos.
 */
class SincronizarPlanesPasarela extends Command
{
    protected $signature = 'nodico:sincronizar-planes-pasarela
        {--pasarela= : openpay | bbva (por defecto, la activa en PAGOS_PASARELA)}
        {--simular : Muestra qué se crearía sin llamar a Openpay}';

    protected $description = 'Crea en Openpay los planes de las membresías que se renuevan solas.';

    public function handle(SuscripcionesOpenpay $suscripciones): int
    {
        $pasarela = (string) ($this->option('pasarela') ?: config('pagos.pasarela'));

        if (! in_array($pasarela, ['openpay', 'bbva'], true) || ! ClienteOpenpay::para($pasarela)->conSuscripciones()) {
            $this->error("«{$pasarela}» no usa planes ni suscripciones (en BBVA se encienden con BBVA_SUSCRIPCIONES).");

            return self::FAILURE;
        }

        if (! ClienteOpenpay::para($pasarela)->configurado()) {
            $prefijo = strtoupper($pasarela);
            $this->error("Faltan {$prefijo}_MERCHANT_ID, {$prefijo}_LLAVE_PRIVADA o la afiliación en el .env.");

            return self::FAILURE;
        }

        try {
            $resultado = $suscripciones->sincronizarPlanes($pasarela, (bool) $this->option('simular'));
        } catch (ErrorDeOpenpay $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Plan', 'Acción', 'Plan de Openpay'], array_map(
            fn ($f) => [$f['plan'], $f['accion'], $f['id'] ?? '—'],
            $resultado,
        ));

        return self::SUCCESS;
    }
}
