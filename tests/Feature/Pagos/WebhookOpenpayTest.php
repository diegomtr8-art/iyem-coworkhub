<?php

namespace Tests\Feature\Pagos;

use App\Enums\EstadoCuenta;
use App\Models\CargoPasarela;
use App\Models\Plane;
use App\Models\SuscripcionPasarela;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Migración a Openpay — paso 4: el webhook.
 *
 * Openpay no firma sus avisos: solo HTTP Basic. Se comprueba que sin esas
 * credenciales se rechaza todo, que el cuerpo del aviso nunca decide nada (se
 * consulta a Openpay), y que un aviso repetido no activa dos veces.
 */
class WebhookOpenpayTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://sandbox-api.openpay.mx/v1/mop/';

    private array $cargoEnOpenpay = ['status' => 'completed'];

    private array $suscripcionEnOpenpay = ['status' => 'trial', 'current_period_number' => 0];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pagos.pasarela'                   => 'openpay',
            'pagos.openpay.merchant_id'        => 'mop',
            'pagos.openpay.llave_privada'      => 'sk_openpay_inventada',
            'pagos.openpay.sandbox'            => true,
            'pagos.openpay.webhook_usuario'    => 'openpay-avisos',
            'pagos.openpay.webhook_contrasena' => 'una-contrasena-larga-de-prueba',
        ]);

        Http::fake(function (Request $p) {
            if ($p->method() === 'GET' && preg_match('#/charges/(tr_\w+)$#', $p->url(), $m)) {
                $fila = CargoPasarela::where('transaccion_id', $m[1])->first();

                return Http::response([
                    'id' => $m[1], 'amount' => $fila?->importe, 'order_id' => $fila?->order_id, 'currency' => 'MXN',
                    ...$this->cargoEnOpenpay,
                ]);
            }

            if ($p->method() === 'GET' && str_ends_with($p->url(), '/subscriptions/sub_1')) {
                return Http::response(['id' => 'sub_1', ...$this->suscripcionEnOpenpay]);
            }

            return Http::response(['error_code' => 1005, 'http_code' => 404, 'description' => 'no fingido'], 404);
        });
    }

    private function aviso(array $cuerpo, ?string $usuario = 'openpay-avisos', ?string $contrasena = 'una-contrasena-larga-de-prueba')
    {
        $cabeceras = $usuario !== null ? ['Authorization' => 'Basic '.base64_encode("{$usuario}:{$contrasena}")] : [];

        return $this->postJson('/openpay/webhook', $cuerpo, $cabeceras);
    }

    private function cargoPendiente(User $miembro, Plane $plan): CargoPasarela
    {
        return CargoPasarela::create([
            'user_id' => $miembro->id, 'plan_id' => $plan->id, 'pasarela' => 'openpay',
            'order_id' => 'nod-tr_1', 'transaccion_id' => 'tr_1', 'cliente_pasarela_id' => 'cus_1',
            'importe' => (float) $plan->precio, 'estado' => CargoPasarela::PENDIENTE,
        ]);
    }

    // ── Autenticidad ────────────────────────────────────────────────────────

    public function test_sin_credenciales_o_con_credenciales_malas_se_rechaza(): void
    {
        $cuerpo = ['type' => 'charge.succeeded', 'transaction' => ['id' => 'tr_1']];

        $this->aviso($cuerpo, usuario: null)->assertStatus(401);
        $this->aviso($cuerpo, contrasena: 'otra')->assertStatus(401);

        Http::assertNothingSent();
    }

    public function test_sin_credenciales_configuradas_se_rechaza_todo(): void
    {
        config(['pagos.openpay.webhook_usuario' => null, 'pagos.openpay.webhook_contrasena' => null]);

        $this->aviso(['type' => 'verification', 'verification_code' => 'ABC'], usuario: '', contrasena: '')
            ->assertStatus(401);
    }

    public function test_la_verificacion_del_registro_responde_200(): void
    {
        $this->aviso(['type' => 'verification', 'event_date' => now()->toIso8601String(), 'verification_code' => 'UY1qqrxw'])
            ->assertOk();

        Http::assertNothingSent();
    }

    // ── El cuerpo no decide nada ────────────────────────────────────────────

    public function test_un_cargo_pagado_se_confirma_consultando_a_openpay(): void
    {
        $plan = Plane::factory()->dayPass()->create();
        $miembro = User::factory()->miembro()->create();
        $cargo = $this->cargoPendiente($miembro, $plan);

        $this->aviso(['type' => 'charge.succeeded', 'transaction' => ['id' => 'tr_1', 'status' => 'completed', 'customer_id' => 'cus_1']])
            ->assertOk();

        Http::assertSent(fn (Request $p) => $p->method() === 'GET' && $p->url() === self::BASE.'customers/cus_1/charges/tr_1');
        $this->assertSame(CargoPasarela::COMPLETADO, $cargo->refresh()->estado);
        $this->assertSame(1, $miembro->suscripciones()->count());
    }

    public function test_si_el_aviso_dice_pagado_pero_openpay_no_nada_se_activa(): void
    {
        $plan = Plane::factory()->dayPass()->create();
        $miembro = User::factory()->miembro()->create();
        $cargo = $this->cargoPendiente($miembro, $plan);
        $this->cargoEnOpenpay = ['status' => 'charge_pending'];

        $this->aviso(['type' => 'charge.succeeded', 'transaction' => ['id' => 'tr_1', 'status' => 'completed', 'amount' => 79]])
            ->assertOk();

        $this->assertSame(CargoPasarela::PENDIENTE, $cargo->refresh()->estado);
        $this->assertSame(0, $miembro->suscripciones()->count());
    }

    public function test_un_aviso_con_un_id_inventado_no_hace_nada(): void
    {
        $this->aviso(['type' => 'charge.succeeded', 'transaction' => ['id' => 'tr_inventado', 'customer_id' => 'cus_nadie']])
            ->assertOk();

        Http::assertNothingSent();
    }

    public function test_el_mismo_aviso_dos_veces_activa_una_sola_vez(): void
    {
        $plan = Plane::factory()->dayPass()->create();
        $miembro = User::factory()->miembro()->create();
        $this->cargoPendiente($miembro, $plan);
        $cuerpo = ['type' => 'charge.succeeded', 'transaction' => ['id' => 'tr_1', 'customer_id' => 'cus_1']];

        $this->aviso($cuerpo)->assertOk();
        $this->aviso($cuerpo)->assertOk();

        $this->assertSame(1, $miembro->suscripciones()->count());
    }

    // ── Suscripciones ───────────────────────────────────────────────────────

    public function test_un_cobro_mensual_rechazado_sincroniza_y_suspende(): void
    {
        Notification::fake();
        $plan = Plane::factory()->nodoPro()->create(['cobro_recurrente' => true]);
        $miembro = User::factory()->miembro()->create();
        SuscripcionPasarela::create([
            'user_id' => $miembro->id, 'plan_id' => $plan->id, 'pasarela' => 'openpay',
            'suscripcion_id' => 'sub_1', 'cliente_id' => 'cus_1', 'tarjeta_id' => 'card_1',
            'plan_pasarela_id' => 'plan_1', 'estado' => 'active', 'periodo_actual' => 1,
        ]);
        $this->suscripcionEnOpenpay = ['status' => 'past_due', 'current_period_number' => 1];

        $this->aviso(['type' => 'subscription.charge.failed', 'transaction' => ['id' => 'tr_mensual', 'customer_id' => 'cus_1']])
            ->assertOk();

        $this->assertSame('past_due', SuscripcionPasarela::sole()->estado);
        $this->assertSame(EstadoCuenta::Suspendida, $miembro->refresh()->estado);
    }

    public function test_un_cobro_mensual_pagado_renueva_por_la_consulta(): void
    {
        $plan = Plane::factory()->nodoPro()->create(['cobro_recurrente' => true]);
        $miembro = User::factory()->miembro()->create();
        SuscripcionPasarela::create([
            'user_id' => $miembro->id, 'plan_id' => $plan->id, 'pasarela' => 'openpay',
            'suscripcion_id' => 'sub_1', 'cliente_id' => 'cus_1', 'tarjeta_id' => 'card_1',
            'plan_pasarela_id' => 'plan_1', 'estado' => 'trial', 'periodo_actual' => 0,
        ]);
        $this->suscripcionEnOpenpay = ['status' => 'active', 'current_period_number' => 1];

        // El cobro mensual lo crea Openpay: no es un cargo de Nódico.
        $this->aviso(['type' => 'charge.succeeded', 'transaction' => ['id' => 'tr_mensual', 'customer_id' => 'cus_1']])->assertOk();
        $this->aviso(['type' => 'charge.succeeded', 'transaction' => ['id' => 'tr_mensual', 'customer_id' => 'cus_1']])->assertOk();

        $this->assertSame(1, $miembro->suscripciones()->count(), 'Una renovación por periodo, aunque el aviso llegue dos veces.');
        $this->assertSame(1, SuscripcionPasarela::sole()->periodo_actual);
    }
}
