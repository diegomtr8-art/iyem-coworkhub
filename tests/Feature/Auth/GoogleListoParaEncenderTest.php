<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

/**
 * Fase 6 — el ingreso con Google queda listo esperando credenciales.
 *
 * El botón y la ruta dependían solo del interruptor: encenderlo sin
 * credenciales dibujaba un botón que tronaba al pulsarlo. Ahora hacen falta
 * las dos cosas. Y quien ya tenía cuenta con contraseña y entra por primera
 * vez con Google se entera de que su cuenta quedó vinculada.
 */
class GoogleListoParaEncenderTest extends TestCase
{
    use RefreshDatabase;

    private function credenciales(): array
    {
        return [
            'client_id'     => 'id-de-prueba.apps.googleusercontent.com',
            'client_secret' => 'secreto-de-prueba',
            'redirect'      => 'https://prueba.nodico.com.mx/acceso/google/retorno',
        ];
    }

    private function botonDeGoogle(): bool
    {
        $visible = null;
        $this->get('/login')->assertInertia(function ($pagina) use (&$visible) {
            $visible = $pagina->toArray()['props']['proveedores']['google'];
        });

        return (bool) $visible;
    }

    public function test_encendido_sin_credenciales_no_hay_boton_ni_ruta(): void
    {
        config(['nodico.acceso.google' => true, 'services.google' => ['client_id' => null, 'client_secret' => null, 'redirect' => null]]);

        $this->assertFalse($this->botonDeGoogle(), 'Sin credenciales no se dibuja un botón que truena.');
        $this->get('/acceso/google')->assertNotFound();
    }

    public function test_con_credenciales_pero_apagado_tampoco(): void
    {
        config(['nodico.acceso.google' => false, 'services.google' => $this->credenciales()]);

        $this->assertFalse($this->botonDeGoogle());
        $this->get('/acceso/google')->assertNotFound();
    }

    public function test_encendido_y_con_credenciales_aparece_el_boton(): void
    {
        config(['nodico.acceso.google' => true, 'services.google' => $this->credenciales()]);

        $this->assertTrue($this->botonDeGoogle());
        $this->get('/register')->assertInertia(fn ($p) => $p->where('proveedores.google', true));
    }

    public function test_quien_ya_tenia_contrasena_se_entera_de_que_quedo_vinculada(): void
    {
        config(['nodico.acceso.google' => true, 'services.google' => $this->credenciales()]);
        $existente = User::factory()->miembro()->create(['email' => 'ya@example.com']);

        $externo = Mockery::mock('Laravel\Socialite\Two\User');
        $externo->shouldReceive('getId')->andReturn('google-9');
        $externo->shouldReceive('getEmail')->andReturn('ya@example.com');
        $externo->shouldReceive('getName')->andReturn('Ya Existía');
        $externo->shouldReceive('getAvatar')->andReturn(null);
        $externo->user = ['email_verified' => true];

        $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $driver->shouldReceive('enablePkce')->andReturnSelf();
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($externo);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get('/acceso/google/retorno')
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'vinculamos') && str_contains($m, 'contraseña'));

        $this->assertAuthenticatedAs($existente);
    }
}
