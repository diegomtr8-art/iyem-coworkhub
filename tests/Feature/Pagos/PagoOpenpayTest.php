<?php

namespace Tests\Feature\Pagos;

use App\Models\CargoPasarela;
use App\Models\ClientePasarela;
use App\Models\Plane;
use App\Models\User;
use App\Servicios\Pagos\Contratos\PasarelaDePagos;
use App\Servicios\Pagos\PasarelaOpenpay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\ApiMovil\ApiMovil;
use Tests\TestCase;

/**
 * Migración a Openpay — pasos 1 y 2: cobro con la tarjeta tecleada en Nódico,
 * cliente de Openpay por miembro y tarjeta guardada en los planes que se
 * renuevan (docs/PAGOS-OPENPAY.md).
 *
 * Lo que más importa: el importe en pesos, que el cliente se crea una sola
 * vez, que la tarjeta se guarda en Openpay (no en Nódico), que el rechazo por
 * riesgo se reintenta con 3-D Secure, y que nada se activa sin consultar el
 * cargo.
 */
class PagoOpenpayTest extends TestCase
{
    use ApiMovil;
    use RefreshDatabase;

    private const BASE = 'https://sandbox-api.openpay.mx/v1/mop/';

    /** Peticiones de creación de cargo recibidas, en orden. */
    private array $cargosPedidos = [];

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
    }

    /**
     * Openpay de mentira: crea clientes, guarda tarjetas y cobra. `$cargo`
     * decide qué responde el cobro (o el primer cobro, si se da `$segundo`).
     */
    private function fingirOpenpay(array $cargo = ['status' => 'completed'], ?array $segundo = null, ?array $errorCliente = null): void
    {
        $this->cargosPedidos = [];
        $contador = 0;

        Http::fake(function (Request $p) use ($cargo, $segundo, $errorCliente, &$contador) {
            $url = $p->url();

            if ($p->method() === 'POST' && $url === self::BASE.'customers') {
                return $errorCliente
                    ? Http::response($errorCliente, $errorCliente['http_code'])
                    : Http::response(['id' => 'cus_nuevo', 'name' => $p['name'], 'email' => $p['email']]);
            }

            if ($p->method() === 'GET' && str_starts_with($url, self::BASE.'customers?')) {
                return Http::response([['id' => 'cus_recuperado']]);
            }

            if ($p->method() === 'POST' && str_ends_with($url, '/cards')) {
                return Http::response([
                    'id' => 'card_guardada', 'brand' => 'visa', 'card_number' => '411111XXXXXX1111',
                    'expiration_month' => '12', 'expiration_year' => '30',
                ]);
            }

            if ($p->method() === 'POST' && str_ends_with($url, '/charges')) {
                $this->cargosPedidos[] = ['url' => $url, 'datos' => $p->data()];
                $respuesta = ($contador++ === 0 || ! $segundo) ? $cargo : $segundo;

                if (isset($respuesta['error_code'])) {
                    return Http::response($respuesta, $respuesta['http_code']);
                }

                return Http::response([
                    'id'       => 'tr_'.$contador,
                    'amount'   => $p['amount'],
                    'order_id' => $p['order_id'],
                    'currency' => 'MXN',
                    ...$respuesta,
                ]);
            }

            if ($p->method() === 'GET' && preg_match('#/charges/(tr_\d+)$#', $url, $m)) {
                $fila = CargoPasarela::where('transaccion_id', $m[1])->first();

                return Http::response([
                    'id' => $m[1], 'status' => 'completed', 'amount' => $fila?->importe,
                    'order_id' => $fila?->order_id, 'currency' => 'MXN',
                    'card' => ['brand' => 'visa', 'card_number' => '411111XXXXXX1111'],
                ]);
            }

            return Http::response(['error_code' => 1005, 'description' => 'no fingido: '.$url, 'http_code' => 404], 404);
        });
    }

    private function pagarConToken(User $miembro, Plane $plan, array $cabeceras = [])
    {
        return $this->actingAs($miembro)->post(
            route('portal.contratar.tarjeta.token', $plan),
            ['token_id' => 'tok_abc', 'device_session_id' => 'disp_123'],
            $cabeceras,
        );
    }

    // ── La costura ──────────────────────────────────────────────────────────

    public function test_con_openpay_se_inyecta_la_pasarela_de_openpay(): void
    {
        $pasarela = app(PasarelaDePagos::class);

        $this->assertInstanceOf(PasarelaOpenpay::class, $pasarela);
        $this->assertSame('openpay', $pasarela->nombre());
        $this->assertSame('Openpay', $pasarela->etiqueta());
        $this->assertTrue($pasarela->capturaEnNodico(), 'Openpay captura la tarjeta en Nódico.');
    }

    public function test_la_pantalla_de_pago_recibe_solo_datos_publicos_de_openpay(): void
    {
        $plan = Plane::factory()->nodoPro()->create(['cobro_recurrente' => true]);

        $respuesta = $this->actingAs(User::factory()->miembro()->create())
            ->get(route('portal.contratar.tarjeta', $plan))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Portal/Pago')
                ->where('pasarela', 'openpay')
                ->where('captura', 'token')
                ->where('guardaTarjeta', true)
                ->where('openpay.merchant_id', 'mop')
                ->where('openpay.llave_publica', 'pk_openpay_inventada'));

        $this->assertStringNotContainsString('sk_openpay_inventada', $respuesta->getContent());
    }

    // ── El importe ──────────────────────────────────────────────────────────

    public static function planes(): array
    {
        return [
            'Day-Pass'    => ['dayPass', 79],
            'Nódico Flex' => ['flex', 249],
            'Nodo Pro'    => ['nodoPro', 599],
            'Nodo Match'  => ['nodoMatch', 799],
        ];
    }

    #[DataProvider('planes')]
    public function test_a_openpay_se_le_manda_el_importe_exacto_en_pesos(string $estado, int $pesos): void
    {
        $this->fingirOpenpay();
        $plan = Plane::factory()->{$estado}()->create();

        $this->pagarConToken(User::factory()->miembro()->create(), $plan);

        $this->assertCount(1, $this->cargosPedidos);
        $this->assertEquals($pesos, $this->cargosPedidos[0]['datos']['amount']);
        $this->assertNotEquals($pesos * 100, $this->cargosPedidos[0]['datos']['amount']);
        $this->assertArrayNotHasKey('affiliation_bbva', $this->cargosPedidos[0]['datos'], 'Openpay no usa afiliación.');
    }

    // ── Pago único: cliente, token, consulta ────────────────────────────────

    public function test_un_pago_unico_crea_el_cliente_y_cobra_con_el_token_a_nivel_cliente(): void
    {
        $this->fingirOpenpay();
        $plan = Plane::factory()->dayPass()->create();
        $miembro = User::factory()->miembro()->create(['name' => 'Ana López Pérez', 'email' => 'ana@ejemplo.mx']);

        $this->pagarConToken($miembro, $plan)
            ->assertRedirect(route('portal.pago.confirmando', ['cargo' => CargoPasarela::sole()->id]));

        Http::assertSent(fn (Request $p) => $p->method() === 'POST' && $p->url() === self::BASE.'customers'
            && $p['external_id'] === "nodico-testing-{$miembro->id}"
            && $p['requires_account'] === false
            && $p['name'] === 'Ana' && $p['last_name'] === 'López Pérez');

        $cargo = $this->cargosPedidos[0];
        $this->assertSame(self::BASE.'customers/cus_nuevo/charges', $cargo['url']);
        $this->assertSame('tok_abc', $cargo['datos']['source_id']);
        $this->assertSame('disp_123', $cargo['datos']['device_session_id']);
        $this->assertArrayNotHasKey('customer', $cargo['datos'], 'El cargo del cliente no repite sus datos.');
        $this->assertArrayNotHasKey('use_3d_secure', $cargo['datos'], 'Sin 3-D Secure mientras el antifraude no lo pida.');

        // Se activa por la consulta del cargo, por la ruta del cliente.
        Http::assertSent(fn (Request $p) => $p->method() === 'GET' && $p->url() === self::BASE.'customers/cus_nuevo/charges/tr_1');
        $this->assertDatabaseHas('suscripciones', ['user_id' => $miembro->id, 'plan_id' => $plan->id, 'estatus' => 'Activa']);

        // Pago único: no se guarda la tarjeta.
        Http::assertNotSent(fn (Request $p) => str_ends_with($p->url(), '/cards'));
        $this->assertNull(ClientePasarela::sole()->tarjeta_id);
    }

    public function test_el_cliente_de_openpay_se_crea_una_sola_vez(): void
    {
        $this->fingirOpenpay();
        $miembro = User::factory()->miembro()->create();

        $this->pagarConToken($miembro, Plane::factory()->dayPass()->create());
        $this->pagarConToken($miembro, Plane::factory()->flex()->create());

        Http::assertSentCount(2 /* charges */ + 2 /* consultas */ + 1 /* cliente */);
        $this->assertSame(1, ClientePasarela::count());
    }

    public function test_si_el_cliente_ya_existia_en_openpay_se_recupera(): void
    {
        $this->fingirOpenpay(errorCliente: [
            'category' => 'request', 'error_code' => 2003, 'http_code' => 409,
            'description' => 'Customer with this external_id already exists',
        ]);
        $miembro = User::factory()->miembro()->create();

        $this->pagarConToken($miembro, Plane::factory()->dayPass()->create());

        $this->assertSame('cus_recuperado', ClientePasarela::sole()->cliente_id);
        $this->assertSame(self::BASE.'customers/cus_recuperado/charges', $this->cargosPedidos[0]['url']);
    }

    // ── Planes que se renuevan: la tarjeta queda guardada en Openpay ────────

    public function test_en_un_plan_que_se_renueva_la_tarjeta_se_guarda_en_openpay_y_se_cobra_con_ella(): void
    {
        $this->fingirOpenpay();
        $plan = Plane::factory()->nodoPro()->create(['cobro_recurrente' => true]);
        $miembro = User::factory()->miembro()->create();

        $this->pagarConToken($miembro, $plan);

        Http::assertSent(fn (Request $p) => $p->method() === 'POST' && $p->url() === self::BASE.'customers/cus_nuevo/cards'
            && $p['token_id'] === 'tok_abc' && $p['device_session_id'] === 'disp_123');
        $this->assertSame('card_guardada', $this->cargosPedidos[0]['datos']['source_id']);

        $cliente = ClientePasarela::sole();
        $this->assertSame('card_guardada', $cliente->tarjeta_id);
        $this->assertSame('1111', $cliente->tarjeta_ultimos4);
        $this->assertSame('12/30', $cliente->tarjeta_vence);

        $this->assertSame(
            ['marca' => 'visa', 'ultimos4' => '1111'],
            app(PasarelaDePagos::class)->estadoDeRenovacion($miembro)['metodo_pago'],
        );
    }

    // ── 3-D Secure solo cuando hace falta ───────────────────────────────────

    public function test_un_rechazo_por_riesgo_se_reintenta_con_3ds_y_manda_a_la_pagina_del_banco(): void
    {
        $url3ds = 'https://sandbox-api.openpay.mx/v1/mop/charges/tr_2/redirect/';
        $this->fingirOpenpay(
            cargo: ['category' => 'gateway', 'error_code' => 3005, 'http_code' => 402, 'description' => 'The card was declined by fraud system'],
            segundo: ['status' => 'charge_pending', 'payment_method' => ['type' => 'redirect', 'url' => $url3ds]],
        );
        $miembro = User::factory()->miembro()->create();

        $this->pagarConToken($miembro, Plane::factory()->dayPass()->create(), ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', $url3ds);

        [$primero, $segundo] = $this->cargosPedidos;
        $this->assertArrayNotHasKey('use_3d_secure', $primero['datos']);
        $this->assertTrue($segundo['datos']['use_3d_secure']);
        $this->assertSame(route('portal.pago.banco.regreso'), $segundo['datos']['redirect_url']);
        $this->assertSame($primero['datos']['source_id'], $segundo['datos']['source_id']);
        $this->assertNotSame($primero['datos']['order_id'], $segundo['datos']['order_id'], 'El reintento lleva order_id nuevo.');

        $this->assertSame(0, $miembro->suscripciones()->count(), 'Pendiente del banco: nada activado.');
        $this->assertSame(CargoPasarela::PENDIENTE, CargoPasarela::sole()->estado);
    }

    public function test_con_3ds_siempre_el_primer_cargo_ya_va_con_autenticacion(): void
    {
        config(['pagos.openpay.tres_d_secure' => 'siempre']);
        $this->fingirOpenpay(['status' => 'charge_pending', 'payment_method' => ['type' => 'redirect', 'url' => 'https://x.openpay.mx/3ds']]);

        $this->pagarConToken(User::factory()->miembro()->create(), Plane::factory()->dayPass()->create());

        $this->assertCount(1, $this->cargosPedidos);
        $this->assertTrue($this->cargosPedidos[0]['datos']['use_3d_secure']);
    }

    public function test_una_tarjeta_sin_fondos_se_explica_en_espanol(): void
    {
        $this->fingirOpenpay(['category' => 'gateway', 'error_code' => 3003, 'http_code' => 402, 'description' => 'Insufficient funds']);
        $plan = Plane::factory()->dayPass()->create();

        $this->actingAs(User::factory()->miembro()->create())
            ->from(route('portal.contratar.tarjeta', $plan))
            ->post(route('portal.contratar.tarjeta.token', $plan), ['token_id' => 'tok_abc', 'device_session_id' => 'disp_123'])
            ->assertSessionHasErrors(['plan' => 'La tarjeta no tiene saldo suficiente. Prueba con otra.']);

        $this->assertCount(1, $this->cargosPedidos, 'Un rechazo que no es de riesgo no se reintenta.');
    }

    // ── App: formulario de Openpay por redirección ──────────────────────────

    public function test_la_app_recibe_el_formulario_de_openpay_por_redireccion(): void
    {
        $this->fingirOpenpay(['status' => 'charge_pending', 'payment_method' => ['type' => 'redirect', 'url' => 'https://sandbox-api.openpay.mx/v1/mop/charges/tr_1/card_capture']]);
        $plan = Plane::factory()->dayPass()->create(['activo' => true]);
        $token = $this->tokenDe(User::factory()->miembro()->create());

        $this->apiIdempotente('pagos/tarjeta', ['plan_id' => $plan->id], $token)
            ->assertOk()
            ->assertJsonPath('data.modo', 'redireccion')
            ->assertJsonPath('data.pasarela', 'openpay');

        $datos = $this->cargosPedidos[0]['datos'];
        $this->assertSame(self::BASE.'charges', $this->cargosPedidos[0]['url']);
        $this->assertSame('card', $datos['method']);
        $this->assertFalse($datos['confirm']);
        $this->assertSame(route('pago.banco.regreso-app'), $datos['redirect_url']);
        $this->assertArrayHasKey('customer', $datos);
    }
}
