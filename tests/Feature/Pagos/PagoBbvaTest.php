<?php

namespace Tests\Feature\Pagos;

use App\Models\CargoPasarela;
use App\Models\Plane;
use App\Models\User;
use App\Servicios\Pagos\Contratos\PasarelaDePagos;
use App\Servicios\Pagos\PasarelaBbva;
use App\Servicios\Pagos\PasarelaStripe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\ApiMovil\ApiMovil;
use Tests\TestCase;

/**
 * Migración a BBVA — cobro con el formulario del banco y confirmación por
 * consulta del cargo (docs/PAGOS-BBVA.md).
 *
 * Lo que más importa comprobar:
 *  - **el importe exacto en pesos** que se manda a BBVA (el error más caro:
 *    colar el `* 100` de Stripe cobra cien veces de más);
 *  - que **nada se activa** por llegar a la URL de regreso: solo por lo que
 *    responde la API, y solo para un cargo que Nódico creó;
 *  - que un cargo se procesa **una sola vez** aunque se consulte muchas.
 */
class PagoBbvaTest extends TestCase
{
    use ApiMovil;
    use RefreshDatabase;

    private const BASE = 'https://sand-api.ecommercebbva.com/v1/mtest/';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pagos.pasarela'          => 'bbva',
            'pagos.bbva.merchant_id'  => 'mtest',
            'pagos.bbva.llave_privada' => 'sk_prueba_inventada',
            'pagos.bbva.afiliacion'   => '781500',
            'pagos.bbva.sandbox'      => true,
        ]);
    }

    /** Respuesta de BBVA al crear un cargo con formulario (charge_pending + URL). */
    private function fingirCreacion(string $transaccion = 'trprueba1'): void
    {
        Http::fake([
            self::BASE.'charges' => function (Request $peticion) use ($transaccion) {
                return Http::response([
                    'id'             => $transaccion,
                    'status'         => 'charge_pending',
                    'amount'         => $peticion['amount'],
                    'order_id'       => $peticion['order_id'],
                    'currency'       => 'MXN',
                    'payment_method' => ['type' => 'redirect', 'url' => "https://sand-api.ecommercebbva.com/v1/mtest/charges/{$transaccion}/card_capture"],
                ]);
            },
        ]);
    }

    /** Respuesta de BBVA al consultar un cargo. */
    private function fingirConsulta(CargoPasarela $cargo, string $estado, array $extra = []): void
    {
        Http::fake([
            self::BASE.'charges/'.$cargo->transaccion_id => Http::response([
                'id'       => $cargo->transaccion_id,
                'status'   => $estado,
                'amount'   => $cargo->importe,
                'order_id' => $cargo->order_id,
                'currency' => 'MXN',
                'card'     => ['brand' => 'visa', 'card_number' => '411111XXXXXX1111'],
                ...$extra,
            ]),
        ]);
    }

    private function cargoPendiente(User $miembro, Plane $plan, string $transaccion = 'trprueba1'): CargoPasarela
    {
        return CargoPasarela::create([
            'user_id'        => $miembro->id,
            'plan_id'        => $plan->id,
            'pasarela'       => 'bbva',
            'order_id'       => 'nod-'.$transaccion,
            'transaccion_id' => $transaccion,
            'importe'        => (float) $plan->precio,
            'moneda'         => 'MXN',
            'estado'         => CargoPasarela::PENDIENTE,
            'url_pago'       => 'https://sand-api.ecommercebbva.com/pago',
            'ip_cliente'     => '189.0.0.1',
        ]);
    }

    // ── La costura ──────────────────────────────────────────────────────────

    public function test_el_ajuste_decide_que_pasarela_se_inyecta(): void
    {
        $this->assertInstanceOf(PasarelaBbva::class, app(PasarelaDePagos::class));

        config(['pagos.pasarela' => 'stripe']);
        $this->assertInstanceOf(PasarelaStripe::class, app(PasarelaDePagos::class));
    }

    // ── El importe: el error más caro ───────────────────────────────────────

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
    public function test_a_bbva_se_le_manda_el_importe_exacto_en_pesos(string $estado, int $pesos): void
    {
        $this->fingirCreacion();
        $plan = Plane::factory()->{$estado}()->create();
        $miembro = User::factory()->miembro()->create();

        $this->actingAs($miembro)->post(route('portal.contratar.tarjeta.iniciar', $plan));

        Http::assertSent(function (Request $peticion) use ($pesos) {
            return $peticion->method() === 'POST'
                && $peticion->url() === self::BASE.'charges'
                // En pesos: 599 es $599, no $5.99 ni $59,900.
                && $peticion['amount'] == $pesos
                && $peticion['amount'] !== $pesos * 100
                && $peticion['currency'] === 'MXN'
                && $peticion['affiliation_bbva'] === '781500';
        });

        $this->assertEquals($pesos, CargoPasarela::sole()->importe);
    }

    public function test_cada_llamada_lleva_autenticacion_basica_y_la_ip_del_cliente(): void
    {
        $this->fingirCreacion();
        $plan = Plane::factory()->dayPass()->create();

        $this->actingAs(User::factory()->miembro()->create())
            ->withServerVariables(['REMOTE_ADDR' => '189.203.1.2'])
            ->post(route('portal.contratar.tarjeta.iniciar', $plan));

        Http::assertSent(fn (Request $p) => $p->hasHeader('Authorization', 'Basic '.base64_encode('sk_prueba_inventada:'))
            && $p->hasHeader('X-Forwarded-For', '189.203.1.2'));
    }

    // ── Crear el cargo ──────────────────────────────────────────────────────

    public function test_pagar_manda_al_formulario_del_banco_sin_activar_nada(): void
    {
        $this->fingirCreacion('tr123');
        $plan = Plane::factory()->dayPass()->create();
        $miembro = User::factory()->miembro()->create();

        $this->actingAs($miembro)
            ->post(route('portal.contratar.tarjeta.iniciar', $plan), [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://sand-api.ecommercebbva.com/v1/mtest/charges/tr123/card_capture');

        $cargo = CargoPasarela::sole();
        $this->assertSame(CargoPasarela::PENDIENTE, $cargo->estado);
        $this->assertSame('tr123', $cargo->transaccion_id);
        $this->assertSame(0, $miembro->suscripciones()->count(), 'Crear el cargo no activa nada.');
    }

    public function test_un_segundo_clic_retoma_el_cargo_pendiente_en_vez_de_crear_otro(): void
    {
        $plan = Plane::factory()->dayPass()->create();
        $miembro = User::factory()->miembro()->create();
        $cargo = $this->cargoPendiente($miembro, $plan);
        $this->fingirConsulta($cargo, 'charge_pending');

        $this->actingAs($miembro)
            ->post(route('portal.contratar.tarjeta.iniciar', $plan), [], ['X-Inertia' => 'true'])
            ->assertHeader('X-Inertia-Location', $cargo->url_pago);

        $this->assertSame(1, CargoPasarela::count());
        Http::assertNotSent(fn (Request $p) => $p->method() === 'POST');
    }

    public function test_un_rechazo_al_crear_el_cargo_se_explica_en_espanol(): void
    {
        Http::fake([self::BASE.'charges' => Http::response([
            'category' => 'gateway', 'error_code' => 3003, 'description' => 'The card does not have sufficient funds', 'http_code' => 402,
        ], 402)]);
        $plan = Plane::factory()->dayPass()->create();

        $this->actingAs(User::factory()->miembro()->create())
            ->from(route('portal.contratar.tarjeta', $plan))
            ->post(route('portal.contratar.tarjeta.iniciar', $plan))
            ->assertSessionHasErrors(['plan' => 'La tarjeta no tiene saldo suficiente. Prueba con otra.']);

        $this->assertSame(CargoPasarela::FALLIDO, CargoPasarela::sole()->estado);
    }

    public function test_sin_llaves_la_pantalla_se_ve_en_vista_previa_con_su_motivo(): void
    {
        config(['pagos.bbva.llave_privada' => null]);
        $plan = Plane::factory()->dayPass()->create();

        $this->actingAs(User::factory()->miembro()->create())
            ->get(route('portal.contratar.tarjeta', $plan))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Portal/Pago')
                ->where('vistaPrevia', true)
                ->where('pasarela', 'bbva'));
    }

    // ── Confirmar: solo la API decide ───────────────────────────────────────

    public function test_regresar_del_banco_con_el_cargo_pagado_activa_la_membresia(): void
    {
        $plan = Plane::factory()->dayPass()->create();
        $miembro = User::factory()->miembro()->create();
        $cargo = $this->cargoPendiente($miembro, $plan);
        $this->fingirConsulta($cargo, 'completed');

        $this->actingAs($miembro)
            ->get(route('portal.pago.bbva.regreso', ['id' => $cargo->transaccion_id]))
            ->assertRedirect(route('portal.pago.confirmando', ['cargo' => $cargo->id]));

        $this->assertDatabaseHas('suscripciones', ['user_id' => $miembro->id, 'plan_id' => $plan->id, 'estatus' => 'Activa']);
        $cargo->refresh();
        $this->assertSame(CargoPasarela::COMPLETADO, $cargo->estado);
        $this->assertSame('1111', $cargo->tarjeta_ultimos4);
    }

    public function test_el_mismo_cargo_consultado_varias_veces_activa_una_sola_vez(): void
    {
        $plan = Plane::factory()->dayPass()->create();
        $miembro = User::factory()->miembro()->create();
        $cargo = $this->cargoPendiente($miembro, $plan);
        $this->fingirConsulta($cargo, 'completed');

        // Regreso del banco, recarga de la página y el proceso programado.
        $this->actingAs($miembro)->get(route('portal.pago.bbva.regreso', ['id' => $cargo->transaccion_id]));
        $this->actingAs($miembro)->get(route('portal.pago.bbva.regreso', ['id' => $cargo->transaccion_id]));
        CargoPasarela::whereKey($cargo->id)->update(['estado' => CargoPasarela::PENDIENTE]);
        $this->artisan('nodico:confirmar-cargos')->assertSuccessful();

        $this->assertSame(1, $miembro->suscripciones()->count());
        $this->assertSame(1, \App\Models\EventoPasarela::where('pasarela', 'bbva')->count());
    }

    public function test_un_cargo_fallido_no_activa_nada_y_explica_por_que(): void
    {
        $plan = Plane::factory()->dayPass()->create();
        $miembro = User::factory()->miembro()->create();
        $cargo = $this->cargoPendiente($miembro, $plan);
        $this->fingirConsulta($cargo, 'failed', ['error_message' => 'Tarjeta declinada']);

        $this->actingAs($miembro)->get(route('portal.pago.bbva.regreso', ['id' => $cargo->transaccion_id]));

        $this->assertSame(0, $miembro->suscripciones()->count());
        $this->assertSame(CargoPasarela::FALLIDO, $cargo->refresh()->estado);

        $this->actingAs($miembro)
            ->getJson(route('portal.pago.estado', ['cargo' => $cargo->id]))
            ->assertJsonPath('activa', false)
            ->assertJsonPath('estado', 'fallido');
    }

    public function test_un_cargo_pendiente_no_activa_nada(): void
    {
        $plan = Plane::factory()->dayPass()->create();
        $miembro = User::factory()->miembro()->create();
        $cargo = $this->cargoPendiente($miembro, $plan);
        $this->fingirConsulta($cargo, 'charge_pending');

        $this->actingAs($miembro)->get(route('portal.pago.bbva.regreso', ['id' => $cargo->transaccion_id]));

        $this->assertSame(0, $miembro->suscripciones()->count());
        $this->assertSame(CargoPasarela::PENDIENTE, $cargo->refresh()->estado);
    }

    public function test_un_id_inventado_en_la_url_de_regreso_no_activa_nada(): void
    {
        Http::fake();
        $miembro = User::factory()->miembro()->create();

        $this->actingAs($miembro)
            ->get(route('portal.pago.bbva.regreso', ['id' => 'trinventado']))
            ->assertRedirect(route('portal.suscripcion'));

        $this->assertSame(0, $miembro->suscripciones()->count());
        Http::assertNothingSent();
    }

    public function test_el_cargo_de_otra_persona_no_sirve_para_activar_la_propia(): void
    {
        $plan = Plane::factory()->dayPass()->create();
        $dueno = User::factory()->miembro()->create();
        $intruso = User::factory()->miembro()->create();
        $cargo = $this->cargoPendiente($dueno, $plan);
        $this->fingirConsulta($cargo, 'completed');

        $this->actingAs($intruso)
            ->get(route('portal.pago.bbva.regreso', ['id' => $cargo->transaccion_id]))
            ->assertRedirect(route('portal.suscripcion'));

        $this->assertSame(0, $intruso->suscripciones()->count());
    }

    /**
     * El ataque del id ajeno: un cargo pagado de $79 no puede activar un plan
     * de $599. Si BBVA dice un importe distinto del que se pidió, no se activa
     * nada y queda para revisión.
     */
    public function test_si_bbva_dice_otro_importe_no_se_activa_nada(): void
    {
        $plan = Plane::factory()->nodoPro()->create();
        $miembro = User::factory()->miembro()->create();
        $cargo = $this->cargoPendiente($miembro, $plan);
        $this->fingirConsulta($cargo, 'completed', ['amount' => 79]);

        $this->actingAs($miembro)->get(route('portal.pago.bbva.regreso', ['id' => $cargo->transaccion_id]));

        $this->assertSame(0, $miembro->suscripciones()->count());
        $this->assertSame(CargoPasarela::EN_REVISION, $cargo->refresh()->estado);
    }

    public function test_si_la_persona_cierra_la_pestana_el_proceso_programado_activa_igual(): void
    {
        $plan = Plane::factory()->dayPass()->create();
        $miembro = User::factory()->miembro()->create();
        $cargo = $this->cargoPendiente($miembro, $plan);
        $this->fingirConsulta($cargo, 'completed');

        // Nadie volvió a Nódico: solo corre el proceso programado.
        $this->artisan('nodico:confirmar-cargos')->assertSuccessful();

        $this->assertDatabaseHas('suscripciones', ['user_id' => $miembro->id, 'plan_id' => $plan->id, 'estatus' => 'Activa']);
    }

    public function test_un_cargo_sin_terminar_tras_un_dia_se_da_por_abandonado(): void
    {
        Http::fake();
        $plan = Plane::factory()->dayPass()->create();
        $cargo = $this->cargoPendiente(User::factory()->miembro()->create(), $plan);
        CargoPasarela::whereKey($cargo->id)->update(['created_at' => now()->subHours(25)]);

        $this->artisan('nodico:confirmar-cargos')->assertSuccessful();

        $this->assertSame(CargoPasarela::ABANDONADO, $cargo->refresh()->estado);
        Http::assertNothingSent();
    }

    public function test_pagar_otra_vez_el_mismo_plan_vigente_lo_renueva(): void
    {
        $plan = Plane::factory()->nodoPro()->create();
        $miembro = User::factory()->miembro()->create();

        $primero = $this->cargoPendiente($miembro, $plan, 'trprimero');
        $this->fingirConsulta($primero, 'completed');
        $this->artisan('nodico:confirmar-cargos');
        $fin = $miembro->suscripciones()->where('estatus', 'Activa')->value('fecha_fin');

        $segundo = $this->cargoPendiente($miembro, $plan, 'trsegundo');
        $this->fingirConsulta($segundo, 'completed');
        $this->artisan('nodico:confirmar-cargos');

        $this->assertSame('renovacion', $segundo->refresh()->operacion);
        $this->assertTrue(
            $miembro->suscripciones()->where('estatus', 'Activa')->max('fecha_fin') > $fin,
            'Pagar el periodo siguiente extiende la vigencia.',
        );
    }

    // ── App ─────────────────────────────────────────────────────────────────

    public function test_el_regreso_a_la_app_con_un_id_inventado_no_activa_nada(): void
    {
        Http::fake();

        $this->get(route('pago.bbva.regreso-app', ['id' => 'trinventado']))
            ->assertRedirect('nodico://regreso-banco?error=desconocido');

        Http::assertNothingSent();
    }

    public function test_el_regreso_a_la_app_confirma_y_vuelve_solo_a_la_app(): void
    {
        $plan = Plane::factory()->dayPass()->create();
        $miembro = User::factory()->miembro()->create();
        $cargo = $this->cargoPendiente($miembro, $plan);
        // Una vuelta que no es de la app se ignora: no hay redirección abierta.
        $cargo->update(['origen' => 'app', 'url_vuelta' => 'https://malicioso.example/robar']);
        $this->fingirConsulta($cargo, 'completed');

        $this->get(route('pago.bbva.regreso-app', ['id' => $cargo->transaccion_id]))
            ->assertRedirect('nodico://regreso-banco?cargo='.$cargo->id);

        $this->assertSame(1, $miembro->suscripciones()->count());
    }

    public function test_la_app_recibe_el_formulario_del_banco_y_su_vuelta_se_guarda(): void
    {
        $this->fingirCreacion('trapp');
        $plan = Plane::factory()->dayPass()->create(['activo' => true]);
        $ana = User::factory()->miembro()->create();
        $token = $this->tokenDe($ana);

        $this->api('GET', 'estado')
            ->assertJsonPath('data.pagos.pasarela', 'bbva')
            ->assertJsonPath('data.pagos.llave_publica', null);

        $this->apiIdempotente('pagos/tarjeta', ['plan_id' => $plan->id, 'vuelta' => 'exp://192.168.1.5:8081/--/pago/confirmando'], $token)
            ->assertOk()
            ->assertJsonPath('data.modo', 'redireccion')
            ->assertJsonPath('data.url', 'https://sand-api.ecommercebbva.com/v1/mtest/charges/trapp/card_capture');

        $cargo = CargoPasarela::sole();
        $this->assertSame('app', $cargo->origen);
        $this->assertSame('exp://192.168.1.5:8081/--/pago/confirmando', $cargo->url_vuelta);
        Http::assertSent(fn (Request $p) => $p->method() === 'POST'
            && $p['redirect_url'] === route('pago.bbva.regreso-app'));

        $this->api('GET', 'pagos/tarjeta/estado?cargo_id='.$cargo->id, [], $token)
            ->assertJsonPath('data.activa', false);
    }

    public function test_con_bbva_la_app_no_puede_crear_una_suscripcion_de_stripe(): void
    {
        $plan = Plane::factory()->nodoPro()->create(['activo' => true, 'cobro_recurrente' => true]);
        $token = $this->tokenDe(User::factory()->miembro()->create());

        $this->apiIdempotente('pagos/tarjeta/suscribir', ['plan_id' => $plan->id, 'setup_intent_id' => 'seti_x'], $token)
            ->assertStatus(409);
    }
}
