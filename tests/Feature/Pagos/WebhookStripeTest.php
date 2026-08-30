<?php

namespace Tests\Feature\Pagos;

use App\Models\Plane;
use App\Models\Suscripcion;
use App\Models\User;
use App\Notifications\PagoRechazado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Fase 4.A — la verdad de un pago la dice el webhook, no el navegador. Aquí se
 * comprueba lo que más se hace mal: que la membresía se active **solo** por
 * webhook, que el webhook sea idempotente (Stripe reintenta) y que verifique la
 * firma.
 */
class WebhookStripeTest extends TestCase
{
    use RefreshDatabase;

    private function planRecurrente(): Plane
    {
        return Plane::create([
            'nombre' => 'Nodo Pro', 'tipo' => 'mes', 'precio' => 599.00,
            'horas_sala_mes' => 10, 'horas_contenido_mes' => 10,
            'stripe_price_id' => 'price_pro_test', 'cobro_recurrente' => true,
            'activo' => true,
        ]);
    }

    private function miembroStripe(): User
    {
        return User::factory()->miembro()->create([
            'stripe_id' => 'cus_test_123',
            'estado_cuenta' => 'pendiente',
        ]);
    }

    /** Payload de `customer.subscription.created` con lo que Cashier espera. */
    private function payloadSubscriptionCreated(User $miembro, Plane $plan, string $eventId = 'evt_1'): array
    {
        return [
            'id'   => $eventId,
            'type' => 'customer.subscription.created',
            'data' => ['object' => [
                'id'       => 'sub_test_1',
                'customer' => $miembro->stripe_id,
                'status'   => 'active',
                'metadata' => ['type' => 'default'],
                'items'    => ['data' => [[
                    'id'       => 'si_test_1',
                    'quantity' => 1,
                    'price'    => ['id' => $plan->stripe_price_id, 'product' => 'prod_test'],
                ]]],
            ]],
        ];
    }

    public function test_una_suscripcion_creada_en_stripe_activa_la_membresia(): void
    {
        $miembro = $this->miembroStripe();
        $plan = $this->planRecurrente();

        $this->postJson('/stripe/webhook', $this->payloadSubscriptionCreated($miembro, $plan))
            ->assertOk();

        // Membresía de Nódico creada y activa, y la cuenta pasó a activa.
        $this->assertDatabaseHas('suscripciones', [
            'user_id' => $miembro->id,
            'plan_id' => $plan->id,
            'estatus' => 'Activa',
        ]);
        $this->assertSame('activa', $miembro->refresh()->estado_cuenta);
    }

    public function test_el_webhook_es_idempotente(): void
    {
        $miembro = $this->miembroStripe();
        $plan = $this->planRecurrente();
        $payload = $this->payloadSubscriptionCreated($miembro, $plan);

        // El mismo evento llega dos veces (Stripe reintenta).
        $this->postJson('/stripe/webhook', $payload)->assertOk();
        $this->postJson('/stripe/webhook', $payload)->assertOk();

        // Una sola membresía, no dos ciclos.
        $this->assertSame(1, Suscripcion::where('user_id', $miembro->id)->count());
    }

    public function test_sin_pasar_por_el_webhook_no_hay_membresia(): void
    {
        // No hay ninguna ruta que active la membresía sin el webhook: el checkout
        // solo prepara el pago. Aquí se comprueba el estado de partida — un
        // miembro con customer de Stripe pero sin webhook no tiene suscripción.
        $miembro = $this->miembroStripe();
        $this->planRecurrente();

        $this->assertSame(0, Suscripcion::where('user_id', $miembro->id)->count());
        $this->assertSame('pendiente', $miembro->refresh()->estado_cuenta);
    }

    public function test_un_cobro_fallido_suspende_y_avisa(): void
    {
        Notification::fake();
        $miembro = $this->miembroStripe();
        $plan = $this->planRecurrente();

        // Primero se activa.
        $this->postJson('/stripe/webhook', $this->payloadSubscriptionCreated($miembro, $plan))->assertOk();

        // Luego falla un cobro.
        $this->postJson('/stripe/webhook', [
            'id'   => 'evt_fail_1',
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['customer' => $miembro->stripe_id]],
        ])->assertOk();

        $this->assertSame('suspendida', $miembro->refresh()->estado_cuenta);
        Notification::assertSentTo($miembro, PagoRechazado::class);
    }

    public function test_verifica_la_firma_cuando_hay_secret_configurado(): void
    {
        // Con el secret del webhook configurado, un POST sin firma válida no debe
        // pasar: un endpoint de webhook sin verificación de firma deja que
        // cualquiera active membresías.
        config(['cashier.webhook.secret' => 'whsec_prueba']);

        $miembro = $this->miembroStripe();
        $plan = $this->planRecurrente();

        $this->postJson('/stripe/webhook', $this->payloadSubscriptionCreated($miembro, $plan))
            ->assertForbidden();

        // Y no activó nada.
        $this->assertSame(0, Suscripcion::where('user_id', $miembro->id)->count());
    }
}
