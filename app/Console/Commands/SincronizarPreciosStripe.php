<?php

namespace App\Console\Commands;

use App\Models\Plane;
use Illuminate\Console\Command;
use Laravel\Cashier\Cashier;

/**
 * Fase 4.A — crea en Stripe el producto y el precio de cada plan, y guarda el
 * `stripe_price_id` en la tabla. Así no hay que crearlos a mano en el panel ni
 * copiar identificadores.
 *
 * Idempotente: un plan que ya tiene `stripe_price_id` se salta, salvo con
 * `--forzar` (que crea un precio nuevo; el importe de un precio en Stripe no se
 * puede editar, se reemplaza).
 *
 * Recurrente vs pago único lo decide `cobro_recurrente` del plan.
 */
class SincronizarPreciosStripe extends Command
{
    protected $signature = 'nodico:stripe-precios {--forzar : Recrea el precio aunque el plan ya tenga uno}';

    protected $description = 'Crea en Stripe los precios de los planes y guarda su id.';

    public function handle(): int
    {
        if (! config('cashier.secret')) {
            $this->error('Falta STRIPE_SECRET en el .env. Configúralo antes de sincronizar.');

            return self::FAILURE;
        }

        $stripe = Cashier::stripe();
        $moneda = config('cashier.currency', 'mxn');

        $planes = Plane::where('precio', '>', 0)->get();

        foreach ($planes as $plan) {
            if ($plan->stripe_price_id && ! $this->option('forzar')) {
                $this->line("  {$plan->nombre}: ya tiene precio ({$plan->stripe_price_id}), se salta.");
                continue;
            }

            $producto = $stripe->products->create([
                'name'     => "Nódico — {$plan->nombre}",
                'metadata' => ['plan_id' => (string) $plan->id],
            ]);

            $datosPrecio = [
                'product'     => $producto->id,
                'currency'    => $moneda,
                'unit_amount' => (int) round($plan->precio * 100),
                'metadata'    => ['plan_id' => (string) $plan->id],
            ];

            // Los planes que se renuevan solos llevan precio recurrente mensual;
            // los de pago único, un precio sin recurrencia.
            if ($plan->cobro_recurrente) {
                $datosPrecio['recurring'] = ['interval' => 'month'];
            }

            $precio = $stripe->prices->create($datosPrecio);

            $plan->update(['stripe_price_id' => $precio->id]);

            $tipo = $plan->cobro_recurrente ? 'recurrente/mes' : 'pago único';
            $this->info("  {$plan->nombre}: {$precio->id} ({$tipo}, \${$plan->precio} {$moneda}).");
        }

        $this->info('Precios de Stripe sincronizados.');

        return self::SUCCESS;
    }
}
