<?php

namespace Tests\Feature\Pagos;

use App\Models\Plane;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ApiMovil\ApiMovil;
use Tests\TestCase;

/**
 * Migración a Openpay — paso 6: la app no cobra con tarjeta. Pide un enlace de
 * un solo uso que abre la sesión web y lleva a la página de pago; al terminar,
 * la página regresa a la app.
 *
 * Lo que importa: que el enlace sirva una sola vez, venza, sea de esa persona
 * y ese plan, y que no se pueda usar para mandar a nadie fuera de la app.
 */
class PagoDesdeAppTest extends TestCase
{
    use ApiMovil;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pagos.pasarela'              => 'openpay',
            'pagos.openpay.merchant_id'   => 'mop',
            'pagos.openpay.llave_privada' => 'sk_openpay_inventada',
            'pagos.openpay.llave_publica' => 'pk_openpay_inventada',
        ]);
    }

    private function pedirEnlace(User $miembro, Plane $plan, ?string $vuelta = 'nodico://regreso-banco'): string
    {
        return $this->apiIdempotente('pagos/tarjeta/enlace', ['plan_id' => $plan->id, 'vuelta' => $vuelta], $this->tokenDe($miembro))
            ->assertOk()
            ->json('data.url');
    }

    /**
     * Lo que sigue lo hace el navegador, no la app: el guardia es el de la web
     * (en la vida real cada petición llega sola; en la prueba, el de la API se
     * queda como predeterminado tras pedir el enlace).
     */
    private function comoNavegador(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
    }

    /** La ruta relativa del enlace, para pedirla en la prueba. */
    private function ruta(string $url): string
    {
        return parse_url($url, PHP_URL_PATH);
    }

    public function test_el_enlace_abre_la_sesion_web_y_lleva_a_pagar_ese_plan(): void
    {
        $plan = Plane::factory()->nodoPro()->create(['activo' => true]);
        $ana = User::factory()->miembro()->create();
        $url = $this->pedirEnlace($ana, $plan);

        $this->comoNavegador();
        $this->get($this->ruta($url))->assertRedirect(route('portal.contratar.tarjeta', $plan));

        $this->assertAuthenticatedAs($ana, 'web');
        $this->get(route('portal.contratar.tarjeta', $plan))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Portal/Pago')->where('volverALaApp', 'nodico://regreso-banco'));
    }

    public function test_el_enlace_sirve_una_sola_vez(): void
    {
        $plan = Plane::factory()->dayPass()->create(['activo' => true]);
        $url = $this->pedirEnlace(User::factory()->miembro()->create(), $plan);

        $this->comoNavegador();
        $this->get($this->ruta($url))->assertRedirect(route('portal.contratar.tarjeta', $plan));

        auth('web')->logout();
        $this->get($this->ruta($url))->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_el_enlace_vence_a_los_cinco_minutos(): void
    {
        $plan = Plane::factory()->dayPass()->create(['activo' => true]);
        $url = $this->pedirEnlace(User::factory()->miembro()->create(), $plan);

        $this->travel(6)->minutes();

        $this->comoNavegador();
        $this->get($this->ruta($url))->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_un_enlace_inventado_no_abre_nada(): void
    {
        $this->get('/pago/desde-app/'.str_repeat('a', 64))->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_la_vuelta_solo_puede_ser_a_la_app(): void
    {
        $plan = Plane::factory()->dayPass()->create(['activo' => true]);
        $url = $this->pedirEnlace(User::factory()->miembro()->create(), $plan, 'https://malicioso.example/robar');

        $this->comoNavegador();
        $this->get($this->ruta($url));

        $this->get(route('portal.pago.confirmando'))
            ->assertInertia(fn ($p) => $p->where('volverALaApp', 'nodico://regreso-banco'));
    }

    public function test_sin_pago_con_tarjeta_disponible_no_hay_enlace(): void
    {
        config(['pagos.openpay.llave_privada' => null]);
        $plan = Plane::factory()->dayPass()->create(['activo' => true]);

        $this->apiIdempotente('pagos/tarjeta/enlace', ['plan_id' => $plan->id], $this->tokenDe(User::factory()->miembro()->create()))
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'tarjeta_no_disponible');
    }
}
