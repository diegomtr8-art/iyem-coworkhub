<?php

namespace Tests\Feature\Pagos;

use App\Enums\EstadoCuenta;
use App\Models\CargoPasarela;
use App\Models\ClientePasarela;
use App\Models\Plane;
use App\Models\PlanPasarela;
use App\Models\SuscripcionPasarela;
use App\Models\User;
use App\Notifications\PagoRechazado;
use App\Servicios\Pagos\Contratos\PasarelaDePagos;
use App\Servicios\Pagos\Openpay\SuscripcionesOpenpay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Migración a Openpay — paso 3: el cobro automático de cada periodo.
 *
 * El primer periodo lo cobra Nódico; confirmado ese cobro, el servidor da de
 * alta la suscripción con prueba hasta el último día pagado, y de ahí cobra
 * Openpay. Se comprueba sobre todo: que la suscripción la crea el servidor y
 * no la pantalla, que nunca hay dos, y que cada renovación o rechazo se
 * procesa una sola vez.
 */
class SuscripcionesOpenpayTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://sandbox-api.openpay.mx/v1/mop/';

    /** Lo que responde la consulta de la suscripción en cada prueba. */
    private array $suscripcionEnOpenpay = ['status' => 'trial', 'current_period_number' => 0];

    private bool $altaFalla = false;

    private int $planesCreados = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pagos.pasarela'              => 'openpay',
            'pagos.openpay.merchant_id'   => 'mop',
            'pagos.openpay.llave_privada' => 'sk_openpay_inventada',
            'pagos.openpay.llave_publica' => 'pk_openpay_inventada',
            'pagos.openpay.sandbox'       => true,
        ]);

        $this->fingirOpenpay();
    }

    private function fingirOpenpay(): void
    {
        Http::fake(function (Request $p) {
            $url = $p->url();
            $m = $p->method();

            return match (true) {
                $m === 'POST' && $url === self::BASE.'plans' => Http::response([
                    'id' => 'plan_'.(++$this->planesCreados), 'amount' => $p['amount'], 'status' => 'active',
                ]),
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
                $m === 'POST' && str_ends_with($url, '/subscriptions') => $this->altaFalla
                    ? Http::response(['error_code' => 1000, 'description' => 'caído', 'http_code' => 500], 500)
                    : Http::response([
                        'id' => 'sub_1', 'status' => 'trial', 'cancel_at_period_end' => false,
                        'trial_end_date' => $p['trial_end_date'], 'period_end_date' => $p['trial_end_date'],
                        'charge_date' => now()->parse($p['trial_end_date'])->addDay()->toDateString(),
                        'current_period_number' => 0, 'plan_id' => $p['plan_id'],
                    ]),
                $m === 'PUT' && str_ends_with($url, '/subscriptions/sub_1') => Http::response([
                    'id' => 'sub_1', 'status' => 'trial', 'cancel_at_period_end' => $p['cancel_at_period_end'],
                ]),
                $m === 'GET' && str_ends_with($url, '/subscriptions/sub_1') => Http::response(['id' => 'sub_1', ...$this->suscripcionEnOpenpay]),
                default => Http::response(['error_code' => 1005, 'description' => 'no fingido: '.$m.' '.$url, 'http_code' => 404], 404),
            };
        });
    }

    private function nodoPro(float $precio = 599): Plane
    {
        return Plane::factory()->nodoPro()->create(['cobro_recurrente' => true, 'precio' => $precio]);
    }

    private function pagar(User $miembro, Plane $plan)
    {
        return $this->actingAs($miembro)->post(
            route('portal.contratar.tarjeta.token', $plan),
            ['token_id' => 'tok_abc', 'device_session_id' => 'disp_123'],
        );
    }

    /** Un miembro que ya pagó Nodo Pro y tiene su suscripción en Openpay. */
    private function miembroSuscrito(Plane $plan): User
    {
        $this->artisan('nodico:sincronizar-planes-pasarela');
        $miembro = User::factory()->miembro()->create();
        $this->pagar($miembro, $plan);
        $this->assertSame(1, SuscripcionPasarela::count(), 'Precondición: suscripción dada de alta.');

        return $miembro;
    }

    // ── Planes ──────────────────────────────────────────────────────────────

    public function test_los_planes_que_se_renuevan_se_crean_en_openpay_en_pesos_y_una_sola_vez(): void
    {
        $pro = $this->nodoPro();
        Plane::factory()->dayPass()->create();

        $this->artisan('nodico:sincronizar-planes-pasarela')->assertSuccessful();
        $this->artisan('nodico:sincronizar-planes-pasarela')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $p) => $p->url() === self::BASE.'plans'
            && $p['amount'] == 599 && $p['amount'] != 59900
            && $p['repeat_every'] === 1 && $p['repeat_unit'] === 'month'
            && $p['retry_times'] === 2 && $p['status_after_retry'] === 'unpaid'
            && $p['trial_days'] === 0 && $p['currency'] === 'MXN');

        $this->assertSame('plan_1', PlanPasarela::sole()->plan_pasarela_id);
        $this->assertTrue(app(PasarelaDePagos::class)->renuevaSola($pro));
    }

    public function test_si_cambia_el_precio_se_crea_otro_plan_y_el_anterior_queda_inactivo(): void
    {
        $pro = $this->nodoPro();
        $this->artisan('nodico:sincronizar-planes-pasarela');

        $pro->update(['precio' => 649]);
        $this->assertFalse(app(PasarelaDePagos::class)->renuevaSola($pro), 'Sin sincronizar el precio nuevo, no se ofrece.');

        $this->artisan('nodico:sincronizar-planes-pasarela');

        $this->assertSame(2, PlanPasarela::count());
        $this->assertSame('plan_2', PlanPasarela::where('activo', true)->sole()->plan_pasarela_id);
        $this->assertEquals(649, PlanPasarela::where('activo', true)->sole()->importe);
    }

    // ── Alta ────────────────────────────────────────────────────────────────

    public function test_pagar_un_plan_que_se_renueva_cobra_el_mes_y_da_de_alta_la_suscripcion(): void
    {
        $pro = $this->nodoPro();
        $miembro = $this->miembroSuscrito($pro);

        $cargo = CargoPasarela::sole();
        $this->assertTrue($cargo->suscribir);
        $this->assertSame('card_1', ClientePasarela::sole()->tarjeta_id);

        $membresia = $miembro->suscripciones()->where('estatus', 'Activa')->sole();
        $this->assertTrue((bool) $membresia->auto_renovar);

        // La suscripción la crea el servidor tras confirmar el cobro, con la
        // tarjeta guardada y prueba hasta el último día pagado.
        Http::assertSent(fn (Request $p) => $p->method() === 'POST' && $p->url() === self::BASE.'customers/cus_1/subscriptions'
            && $p['plan_id'] === 'plan_1' && $p['source_id'] === 'card_1'
            && $p['trial_end_date'] === $membresia->fecha_fin->toDateString());

        $suscripcion = SuscripcionPasarela::sole();
        $this->assertSame('trial', $suscripcion->estado);
        $this->assertSame($cargo->id, $suscripcion->cargo_id);
        $this->assertSame($membresia->fecha_fin->addDay()->toDateString(), $suscripcion->proximo_cobro->toDateString());

        $estado = app(PasarelaDePagos::class)->estadoDeRenovacion($miembro);
        $this->assertTrue($estado['tiene_recurrente']);
        $this->assertTrue($estado['renovacion_activa']);
    }

    public function test_nunca_se_crean_dos_suscripciones(): void
    {
        $pro = $this->nodoPro();
        $miembro = $this->miembroSuscrito($pro);
        CargoPasarela::query()->update(['confirmado_en' => now()->subHour()]);

        $this->actingAs($miembro)
            ->from(route('portal.contratar.tarjeta', $pro))
            ->post(route('portal.contratar.tarjeta.token', $pro), ['token_id' => 'tok_otro', 'device_session_id' => 'disp'])
            ->assertSessionHasErrors('plan');

        $this->assertSame(1, CargoPasarela::count(), 'No se cobró otra vez.');
        $this->assertSame(1, SuscripcionPasarela::count());
    }

    public function test_sin_plan_sincronizado_se_paga_el_periodo_sin_suscripcion(): void
    {
        $pro = $this->nodoPro();
        $miembro = User::factory()->miembro()->create();

        $this->pagar($miembro, $pro);

        $this->assertFalse(CargoPasarela::sole()->suscribir);
        $this->assertSame(0, SuscripcionPasarela::count());
        $this->assertSame(1, $miembro->suscripciones()->count(), 'El periodo sí quedó pagado.');
    }

    public function test_si_openpay_falla_al_dar_de_alta_la_suscripcion_se_reintenta_despues(): void
    {
        $pro = $this->nodoPro();
        $this->artisan('nodico:sincronizar-planes-pasarela');
        $miembro = User::factory()->miembro()->create();

        $this->altaFalla = true;
        $this->pagar($miembro, $pro);
        $this->assertSame(0, SuscripcionPasarela::count());
        $this->assertSame(1, $miembro->suscripciones()->count(), 'El cobro del mes ya quedó.');

        $this->altaFalla = false;
        $this->artisan('nodico:sincronizar-suscripciones')->assertSuccessful();

        $this->assertSame(1, SuscripcionPasarela::count());
    }

    // ── Cancelar y reactivar ────────────────────────────────────────────────

    public function test_cancelar_la_renovacion_y_reactivarla(): void
    {
        $pro = $this->nodoPro();
        $miembro = $this->miembroSuscrito($pro);

        $this->actingAs($miembro)->post(route('portal.membresia.cancelar'))->assertSessionHas('success');

        Http::assertSent(fn (Request $p) => $p->method() === 'PUT' && $p['cancel_at_period_end'] === true);
        $this->assertTrue(SuscripcionPasarela::sole()->cancelar_al_final);
        $this->assertFalse((bool) $miembro->suscripciones()->where('estatus', 'Activa')->sole()->auto_renovar);
        $this->assertTrue(app(PasarelaDePagos::class)->estadoDeRenovacion($miembro)['en_periodo_de_gracia']);

        $this->actingAs($miembro)->post(route('portal.membresia.reactivar'))->assertSessionHas('success');

        Http::assertSent(fn (Request $p) => $p->method() === 'PUT' && $p['cancel_at_period_end'] === false);
        $this->assertFalse(SuscripcionPasarela::sole()->cancelar_al_final);
        $this->assertTrue((bool) $miembro->suscripciones()->where('estatus', 'Activa')->sole()->auto_renovar);
    }

    // ── Sincronización: renovación, impago, cancelación ─────────────────────

    public function test_cuando_openpay_cobra_un_periodo_la_membresia_se_renueva_una_sola_vez(): void
    {
        $pro = $this->nodoPro();
        $miembro = $this->miembroSuscrito($pro);
        $finAntes = $miembro->suscripciones()->max('fecha_fin');

        $this->suscripcionEnOpenpay = ['status' => 'active', 'current_period_number' => 1];
        $this->artisan('nodico:sincronizar-suscripciones');
        $this->artisan('nodico:sincronizar-suscripciones');

        $this->assertSame(2, $miembro->suscripciones()->count(), 'Un periodo cobrado, una renovación.');
        $this->assertTrue($miembro->suscripciones()->max('fecha_fin') > $finAntes);
        $this->assertSame(1, SuscripcionPasarela::sole()->periodo_actual);
    }

    public function test_un_cobro_rechazado_suspende_y_si_un_reintento_se_cobra_la_cuenta_vuelve(): void
    {
        Notification::fake();
        $pro = $this->nodoPro();
        $miembro = $this->miembroSuscrito($pro);

        $this->suscripcionEnOpenpay = ['status' => 'past_due', 'current_period_number' => 0];
        $this->artisan('nodico:sincronizar-suscripciones');
        $this->artisan('nodico:sincronizar-suscripciones');

        $this->assertSame(EstadoCuenta::Suspendida, $miembro->refresh()->estado);
        Notification::assertSentToTimes($miembro, PagoRechazado::class, 1);

        // Openpay reintentó solo y esta vez cobró.
        $this->suscripcionEnOpenpay = ['status' => 'active', 'current_period_number' => 1];
        $this->artisan('nodico:sincronizar-suscripciones');

        $this->assertSame(EstadoCuenta::Activa, $miembro->refresh()->estado);
        $this->assertFalse(SuscripcionPasarela::sole()->en_impago);
    }

    public function test_si_openpay_cancela_la_suscripcion_deja_de_renovarse(): void
    {
        $pro = $this->nodoPro();
        $miembro = $this->miembroSuscrito($pro);

        $this->suscripcionEnOpenpay = ['status' => 'cancelled', 'current_period_number' => 0];
        $this->artisan('nodico:sincronizar-suscripciones');

        $this->assertSame('cancelled', SuscripcionPasarela::sole()->estado);
        $this->assertFalse((bool) $miembro->suscripciones()->where('estatus', 'Activa')->sole()->auto_renovar);
        $this->assertFalse(app(PasarelaDePagos::class)->estadoDeRenovacion($miembro)['tiene_recurrente']);
    }
}
