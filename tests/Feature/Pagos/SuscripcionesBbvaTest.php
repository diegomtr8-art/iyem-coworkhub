<?php

namespace Tests\Feature\Pagos;

use App\Models\CargoPasarela;
use App\Models\ClientePasarela;
use App\Models\Plane;
use App\Models\SuscripcionPasarela;
use App\Models\User;
use App\Servicios\Pagos\Contratos\PasarelaDePagos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Renovación automática con Ecommerce BBVA (`BBVA_SUSCRIPCIONES`).
 *
 * Las credenciales del instituto son de Ecommerce BBVA, cuya documentación
 * solo describe cargos, aunque su sandbox acepta clientes, tarjetas, planes,
 * suscripciones y webhooks igual que Openpay. Se comprueba que el interruptor
 * decide de verdad: apagado, BBVA cobra por periodo como antes; encendido,
 * sigue el mismo camino que Openpay, **con la afiliación en cada cargo** y
 * contra las URL de BBVA, y su webhook solo toca lo suyo.
 */
class SuscripcionesBbvaTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://sand-api.ecommercebbva.com/v1/mbv/';

    private array $cargosPedidos = [];

    private array $suscripcionEnBbva = ['status' => 'trial', 'current_period_number' => 0];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pagos.pasarela'                => 'bbva',
            'pagos.bbva.merchant_id'        => 'mbv',
            'pagos.bbva.llave_privada'      => 'sk_bbva_inventada',
            'pagos.bbva.llave_publica'      => 'pk_bbva_inventada',
            'pagos.bbva.afiliacion'         => '1234567',
            'pagos.bbva.sandbox'            => true,
            'pagos.bbva.captura'            => 'token',
            'pagos.bbva.suscripciones'      => true,
            'pagos.bbva.webhook_usuario'    => 'bbva-avisos',
            'pagos.bbva.webhook_contrasena' => 'otra-contrasena-larga-de-prueba',
            // Openpay configurado también, con su propio webhook: no debe
            // poder tocar lo de BBVA.
            'pagos.openpay.merchant_id'        => 'mop',
            'pagos.openpay.llave_privada'      => 'sk_openpay_inventada',
            'pagos.openpay.webhook_usuario'    => 'openpay-avisos',
            'pagos.openpay.webhook_contrasena' => 'una-contrasena-larga-de-prueba',
        ]);

        $planes = 0;

        Http::fake(function (Request $p) use (&$planes) {
            $url = $p->url();
            $m = $p->method();

            if ($m === 'POST' && str_ends_with($url, '/charges')) {
                $this->cargosPedidos[] = ['url' => $url, 'datos' => $p->data()];
            }

            return match (true) {
                ! str_starts_with($url, self::BASE) => Http::response(['error_code' => 1002, 'description' => 'otra plataforma: '.$url, 'http_code' => 401], 401),
                $m === 'POST' && $url === self::BASE.'plans' => Http::response(['id' => 'plan_'.(++$planes), 'amount' => $p['amount']]),
                $m === 'POST' && $url === self::BASE.'customers' => Http::response(['id' => 'cus_1']),
                $m === 'POST' && str_ends_with($url, '/cards') => Http::response([
                    'id' => 'card_1', 'brand' => 'visa', 'card_number' => '411111XXXXXX1111',
                    'expiration_month' => '12', 'expiration_year' => '30',
                ]),
                $m === 'POST' && str_ends_with($url, '/charges') => Http::response([
                    'id' => 'tr_1', 'status' => 'completed', 'amount' => $p['amount'], 'order_id' => $p['order_id'], 'currency' => 'MXN',
                ]),
                $m === 'GET' && str_ends_with($url, '/charges/tr_1') => Http::response([
                    'id' => 'tr_1', 'status' => 'completed', 'currency' => 'MXN',
                    'amount' => CargoPasarela::where('transaccion_id', 'tr_1')->value('importe'),
                    'order_id' => CargoPasarela::where('transaccion_id', 'tr_1')->value('order_id'),
                ]),
                $m === 'POST' && str_ends_with($url, '/subscriptions') => Http::response([
                    'id' => 'sub_1', 'status' => 'trial', 'cancel_at_period_end' => false,
                    'period_end_date' => $p['trial_end_date'], 'current_period_number' => 0,
                    'charge_date' => now()->parse($p['trial_end_date'])->addDay()->toDateString(),
                ]),
                $m === 'GET' && str_ends_with($url, '/subscriptions/sub_1') => Http::response(['id' => 'sub_1', ...$this->suscripcionEnBbva]),
                default => Http::response(['error_code' => 1005, 'description' => 'no fingido: '.$m.' '.$url, 'http_code' => 404], 404),
            };
        });
    }

    private function nodoPro(): Plane
    {
        return Plane::factory()->nodoPro()->create(['cobro_recurrente' => true, 'precio' => 599]);
    }

    private function pagar(User $miembro, Plane $plan)
    {
        return $this->actingAs($miembro)->post(
            route('portal.contratar.tarjeta.token', $plan),
            ['token_id' => 'tok_abc', 'device_session_id' => 'disp_123'],
        );
    }

    public function test_apagado_bbva_cobra_por_periodo_sin_cliente_ni_planes(): void
    {
        config(['pagos.bbva.suscripciones' => false]);
        $plan = $this->nodoPro();

        $this->artisan('nodico:sincronizar-planes-pasarela')->assertFailed();
        $this->assertFalse(app(PasarelaDePagos::class)->renuevaSola($plan));

        $this->pagar(User::factory()->miembro()->create(), $plan);

        $this->assertSame(self::BASE.'charges', $this->cargosPedidos[0]['url'], 'Cargo de comercio, no de cliente.');
        $this->assertSame(0, ClientePasarela::count());
        $this->assertSame(0, SuscripcionPasarela::count());
    }

    public function test_encendido_el_primer_pago_guarda_la_tarjeta_y_da_de_alta_la_suscripcion_en_bbva(): void
    {
        $plan = $this->nodoPro();
        $this->artisan('nodico:sincronizar-planes-pasarela')->assertSuccessful();
        $this->assertTrue(app(PasarelaDePagos::class)->renuevaSola($plan));

        $miembro = User::factory()->miembro()->create();
        $this->pagar($miembro, $plan);

        $cargo = $this->cargosPedidos[0];
        $this->assertSame(self::BASE.'customers/cus_1/charges', $cargo['url']);
        $this->assertSame('card_1', $cargo['datos']['source_id']);
        $this->assertSame('1234567', $cargo['datos']['affiliation_bbva'], 'BBVA exige la afiliación en todo cargo.');

        $suscripcion = SuscripcionPasarela::sole();
        $this->assertSame('bbva', $suscripcion->pasarela);
        $this->assertSame('sub_1', $suscripcion->suscripcion_id);
        Http::assertSent(fn (Request $p) => $p->method() === 'POST' && $p->url() === self::BASE.'customers/cus_1/subscriptions'
            && $p['plan_id'] === 'plan_1' && $p['source_id'] === 'card_1');
    }

    public function test_el_proceso_programado_sincroniza_las_suscripciones_de_bbva(): void
    {
        $plan = $this->nodoPro();
        $this->artisan('nodico:sincronizar-planes-pasarela');
        $miembro = User::factory()->miembro()->create();
        $this->pagar($miembro, $plan);
        $fin = $miembro->suscripciones()->latest('id')->value('fecha_fin');

        $this->suscripcionEnBbva = ['status' => 'active', 'current_period_number' => 1];
        $this->artisan('nodico:sincronizar-suscripciones')->assertSuccessful();

        $this->assertSame(1, SuscripcionPasarela::sole()->periodo_actual);
        $this->assertTrue($miembro->suscripciones()->latest('fecha_fin')->value('fecha_fin')->gt($fin), 'La renovación extiende la membresía.');
    }

    public function test_el_webhook_de_bbva_entra_con_sus_credenciales_y_solo_toca_lo_suyo(): void
    {
        $plan = $this->nodoPro();
        $this->artisan('nodico:sincronizar-planes-pasarela');
        $this->pagar(User::factory()->miembro()->create(), $plan);
        $this->suscripcionEnBbva = ['status' => 'active', 'current_period_number' => 1];

        $aviso = ['type' => 'charge.succeeded', 'transaction' => ['id' => 'tr_renovacion', 'customer_id' => 'cus_1']];

        // Con las credenciales de Openpay se acepta, pero no es su suscripción.
        $this->postJson('/openpay/webhook', $aviso, ['Authorization' => 'Basic '.base64_encode('openpay-avisos:una-contrasena-larga-de-prueba')])->assertOk();
        $this->assertSame(0, SuscripcionPasarela::sole()->periodo_actual);

        $this->postJson('/openpay/webhook', $aviso, ['Authorization' => 'Basic '.base64_encode('bbva-avisos:otra-contrasena-larga-de-prueba')])->assertOk();
        $this->assertSame(1, SuscripcionPasarela::sole()->periodo_actual);

        $this->postJson('/openpay/webhook', $aviso, ['Authorization' => 'Basic '.base64_encode('bbva-avisos:mal')])->assertUnauthorized();
    }
}
