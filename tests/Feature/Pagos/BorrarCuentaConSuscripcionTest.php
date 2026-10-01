<?php

namespace Tests\Feature\Pagos;

use App\Models\Plane;
use App\Models\SuscripcionPasarela;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Borrar la cuenta tiene que cortar la renovación automática en la pasarela
 * del banco, no solo en Stripe. Antes solo se cancelaba Stripe: con Openpay o
 * BBVA, la pasarela seguía cobrando cada mes a una cuenta que ya no existía.
 */
class BorrarCuentaConSuscripcionTest extends TestCase
{
    use RefreshDatabase;

    private bool $pasarelaCaida = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pagos.pasarela'           => 'bbva',
            'pagos.bbva.merchant_id'   => 'mbv',
            'pagos.bbva.llave_privada' => 'sk_bbva_inventada',
            'pagos.bbva.afiliacion'    => '1234567',
            'pagos.bbva.suscripciones' => true,
        ]);

        Http::fake(fn (Request $p) => $this->pasarelaCaida
            ? Http::response(['error_code' => 1000, 'description' => 'caído', 'http_code' => 500], 500)
            : Http::response(['id' => 'sub_1', 'status' => 'active', 'cancel_at_period_end' => $p['cancel_at_period_end'] ?? null]));
    }

    private function miembroConSuscripcion(): User
    {
        $miembro = User::factory()->miembro()->create(['password' => bcrypt('secreta-123')]);
        $plan = Plane::factory()->nodoPro()->create(['cobro_recurrente' => true]);

        SuscripcionPasarela::create([
            'user_id' => $miembro->id, 'plan_id' => $plan->id, 'pasarela' => 'bbva',
            'suscripcion_id' => 'sub_1', 'cliente_id' => 'cus_1', 'tarjeta_id' => 'card_1',
            'plan_pasarela_id' => 'plan_1', 'estado' => 'active', 'cancelar_al_final' => false,
        ]);

        return $miembro;
    }

    public function test_borrar_la_cuenta_cancela_la_renovacion_en_la_pasarela_del_banco(): void
    {
        $miembro = $this->miembroConSuscripcion();

        $this->actingAs($miembro)->withSession(['auth.password_confirmed_at' => time()])->delete(route('profile.destroy'), ['password' => 'secreta-123']);

        Http::assertSent(fn (Request $p) => $p->method() === 'PUT'
            && str_ends_with($p->url(), '/customers/cus_1/subscriptions/sub_1')
            && $p['cancel_at_period_end'] === true);
        $this->assertModelMissing($miembro);
        $this->assertTrue(SuscripcionPasarela::sole()->cancelar_al_final);
    }

    public function test_si_la_pasarela_no_responde_no_se_borra_nada(): void
    {
        $miembro = $this->miembroConSuscripcion();
        $this->pasarelaCaida = true;

        $this->actingAs($miembro)->withSession(['auth.password_confirmed_at' => time()])->delete(route('profile.destroy'), ['password' => 'secreta-123'])
            ->assertSessionHasErrors('password');

        $this->assertModelExists($miembro);
    }
}
