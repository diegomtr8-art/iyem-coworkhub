<?php

namespace Tests\Feature\Auth;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'MiClaveNodico2026';

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertStatus(200);
    }

    /**
     * B — El alta no inicia sesión.
     *
     * Abrir sesión sobre una dirección que nadie ha demostrado suya es el
     * problema de A.2 en pequeño, y además es lo que hacía distinguibles el
     * alta nueva y el correo repetido.
     */
    public function test_al_registrarse_no_se_inicia_sesion_y_sale_el_correo_de_verificacion(): void
    {
        Notification::fake();

        $respuesta = $this->post('/register', [
            'name'                  => 'Persona Nueva',
            'email'                 => 'nueva@example.com',
            'password'              => self::CLAVE,
            'password_confirmation' => self::CLAVE,
        ]);

        $this->assertGuest();
        $respuesta->assertRedirect(route('registro.revisa-tu-correo', absolute: false));

        $usuario = User::where('email', 'nueva@example.com')->firstOrFail();

        $this->assertFalse($usuario->hasVerifiedEmail());
        $this->assertSame(RolUsuario::Miembro, $usuario->rol);
        $this->assertSame(EstadoCuenta::Pendiente, $usuario->estado);

        Notification::assertSentTo($usuario, VerifyEmail::class);
    }

    public function test_la_pantalla_de_revisa_tu_correo_muestra_la_direccion(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name'                  => 'Persona Nueva',
            'email'                 => 'nueva@example.com',
            'password'              => self::CLAVE,
            'password_confirmation' => self::CLAVE,
        ]);

        $this->get(route('registro.revisa-tu-correo'))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Auth/RevisaTuCorreo')
                ->where('correo', 'nueva@example.com'));
    }

    /** Entrar a pelo a esa URL, sin venir del formulario, no dice nada de nadie. */
    public function test_la_pantalla_de_revisa_tu_correo_no_se_puede_visitar_suelta(): void
    {
        $this->get(route('registro.revisa-tu-correo'))
            ->assertRedirect(route('register', absolute: false));
    }

    public function test_sin_verificar_el_correo_no_se_entra_al_portal(): void
    {
        $sinVerificar = User::factory()->unverified()->miembro()->create();

        $this->actingAs($sinVerificar)
            ->get('/portal')
            ->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_con_el_correo_verificado_si_se_entra_al_portal(): void
    {
        $miembro = User::factory()->miembro()->create();

        $this->actingAs($miembro)->get('/portal')->assertOk();
    }

    /** B — Las reglas de contraseña aplican también en el alta. */
    public function test_una_contrasena_corta_o_sin_numeros_no_pasa(): void
    {
        foreach (['Corta1', 'solamenteletras'] as $intento) {
            $this->post('/register', [
                'name'                  => 'Persona Nueva',
                'email'                 => 'otra' . strlen($intento) . '@example.com',
                'password'              => $intento,
                'password_confirmation' => $intento,
            ])->assertSessionHasErrors('password');
        }

        $this->assertSame(0, User::count());
    }
}
