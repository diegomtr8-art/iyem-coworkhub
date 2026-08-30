<?php

namespace App\Http\Controllers;

use App\Models\EventoStripe;
use App\Models\Plane;
use App\Models\User;
use App\Servicios\Pagos\ActivadorDeMembresia;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fase 4.A — el webhook de Stripe es quien activa las membresías.
 *
 * Hereda de Cashier, que ya **verifica la firma** de cada webhook (mientras
 * `STRIPE_WEBHOOK_SECRET` esté configurado): un endpoint de webhook sin firma es
 * una puerta abierta para que cualquiera active membresías. Aquí solo se añade
 * lo propio de Nódico —crear la suscripción y abrir el ciclo—, y siempre:
 *
 *  - **por webhook, no por el navegador**: la página de retorno solo consulta;
 *  - **idempotente**: cada evento se procesa una vez (Stripe reintenta), con el
 *    id del evento como candado (ver `EventoStripe`);
 *  - dejando que Cashier haga su parte primero (`parent::`), que mantiene al día
 *    su propia tabla de suscripciones.
 */
class StripeWebhookController extends CashierWebhookController
{
    public function __construct(private readonly ActivadorDeMembresia $activador)
    {
        parent::__construct();
    }

    /** Primera compra de una suscripción recurrente (Nodo Pro / Match). */
    protected function handleCustomerSubscriptionCreated(array $payload): Response
    {
        $respuesta = parent::handleCustomerSubscriptionCreated($payload);

        EventoStripe::procesarUnaVez($payload['id'], $payload['type'], function () use ($payload) {
            [$miembro, $plan] = $this->miembroYPlan($payload);
            if ($miembro && $plan) {
                $this->activador->activar($miembro, $plan);
            }
        });

        return $respuesta;
    }

    /** Cobro de un ciclo: la primera factura crea, las siguientes renuevan. */
    protected function handleInvoicePaymentSucceeded(array $payload): Response
    {
        $respuesta = parent::handleInvoicePaymentSucceeded($payload);

        $razon = $payload['data']['object']['billing_reason'] ?? null;

        // `subscription_create` ya lo cubre `customer.subscription.created`; aquí
        // solo interesan las renovaciones, para no abrir el ciclo dos veces.
        if ($razon === 'subscription_cycle') {
            EventoStripe::procesarUnaVez($payload['id'], $payload['type'], function () use ($payload) {
                [$miembro, $plan] = $this->miembroYPlan($payload);
                if ($miembro && $plan) {
                    $this->activador->renovar($miembro, $plan);
                }
            });
        }

        return $respuesta;
    }

    /** Un cobro recurrente falló: suspender y avisar. */
    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        EventoStripe::procesarUnaVez($payload['id'], $payload['type'], function () use ($payload) {
            $miembro = $this->miembroDe($payload);
            if ($miembro) {
                $this->activador->suspenderPorImpago($miembro);
            }
        });

        return $this->successMethod();
    }

    /** La suscripción se canceló o llegó a su fin: dejar de renovar. */
    protected function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        $respuesta = parent::handleCustomerSubscriptionDeleted($payload);

        EventoStripe::procesarUnaVez($payload['id'], $payload['type'], function () use ($payload) {
            $miembro = $this->miembroDe($payload);
            if ($miembro) {
                $this->activador->detenerRenovacion($miembro);
            }
        });

        return $respuesta;
    }

    /** Pago único (Day-Pass / Nódico Flex): activar por el periodo del plan. */
    protected function handlePaymentIntentSucceeded(array $payload): Response
    {
        EventoStripe::procesarUnaVez($payload['id'], $payload['type'], function () use ($payload) {
            $objeto = $payload['data']['object'] ?? [];
            $planId = $objeto['metadata']['plan_id'] ?? null;
            $userId = $objeto['metadata']['user_id'] ?? null;

            // Sin metadata de Nódico no es un pago de membresía (puede ser la
            // primera factura de una suscripción, que ya cubre otro handler).
            if (! $planId || ! $userId) {
                return;
            }

            $miembro = User::find($userId);
            $plan = Plane::find($planId);
            if ($miembro && $plan) {
                $this->activador->activar($miembro, $plan);
            }
        });

        return $this->successMethod();
    }

    // ── Resolución de miembro y plan desde el payload ────────────────────────

    /**
     * @return array{0: ?User, 1: ?Plane}
     */
    private function miembroYPlan(array $payload): array
    {
        return [$this->miembroDe($payload), $this->planDe($payload)];
    }

    private function miembroDe(array $payload): ?User
    {
        $customer = $payload['data']['object']['customer'] ?? null;

        if (! $customer) {
            return null;
        }

        $miembro = User::where('stripe_id', $customer)->first();

        if (! $miembro) {
            Log::warning('Webhook de Stripe sin miembro para el customer.', [
                'customer' => $customer,
                'evento'   => $payload['id'] ?? null,
            ]);
        }

        return $miembro;
    }

    private function planDe(array $payload): ?Plane
    {
        $objeto = $payload['data']['object'] ?? [];

        // El price id está en distinto sitio según el evento: en la suscripción
        // va en `items`, en la factura en `lines`.
        $priceId = $objeto['items']['data'][0]['price']['id']
            ?? $objeto['lines']['data'][0]['price']['id']
            ?? $objeto['lines']['data'][0]['pricing']['price_details']['price']
            ?? null;

        if (! $priceId) {
            return null;
        }

        return Plane::where('stripe_price_id', $priceId)->first();
    }
}
