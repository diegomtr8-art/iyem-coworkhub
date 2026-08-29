<?php

namespace Tests\Feature\Auth;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
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
            'acepta_legales'        => true,
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
            'acepta_legales'        => true,
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

    /**
     * Regresion encontrada en prueba.nodico.com.mx el 29/08/2026.
     *
     * Con el SMTP mal configurado, la `TransportException` subia hasta el
     * controlador y el registro devolvia un **500 despues de haber creado la
     * cuenta**: la persona veia una pantalla de error, la cuenta quedaba
     * huerfana y sin verificar, y al reintentar se topaba con que su correo
     * «ya existia».
     *
     * Que el correo no salga es un problema de operacion; que se lleve por
     * delante el registro es un defecto.
     *
     * Se usa un transporte que falla de verdad, no un doble: asi la prueba
     * recorre el mismo camino que recorrio el fallo real, desde la
     * notificacion hasta el controlador.
     */
    public function test_si_el_correo_no_sale_el_registro_no_se_cae(): void
    {
        $this->transporteQueSiempreFalla();

        $respuesta = $this->post('/register', [
            'name'                  => 'Persona Nueva',
            'email'                 => 'nueva@example.com',
            'password'              => self::CLAVE,
            'password_confirmation' => self::CLAVE,
            'acepta_legales'        => true,
        ]);

        // Ni 500 ni excepcion: la persona llega a la misma pantalla de siempre.
        $respuesta->assertRedirect(route('registro.revisa-tu-correo', absolute: false));
        $respuesta->assertSessionHas('correo_enviado', false);

        // Y la cuenta quedo creada, no a medias.
        $this->assertDatabaseHas('users', ['email' => 'nueva@example.com']);

        // La pantalla lo dice, en vez de mandar a esperar un correo inexistente.
        $this->get(route('registro.revisa-tu-correo'))
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Auth/RevisaTuCorreo')
                ->where('correoEnviado', false));
    }

    /**
     * El mismo fallo por el camino del correo ya registrado. Los dos tienen que
     * comportarse igual: si uno se cayera y el otro no, el 500 delataria cual
     * de los dos fue, que es justo lo que la fase B vino a cerrar.
     */
    public function test_si_el_correo_no_sale_el_alta_repetida_tampoco_se_cae(): void
    {
        User::factory()->create(['email' => 'ya@example.com']);

        $this->transporteQueSiempreFalla();

        $this->post('/register', [
            'name'                  => 'Otra Persona',
            'email'                 => 'ya@example.com',
            'password'              => self::CLAVE,
            'password_confirmation' => self::CLAVE,
            'acepta_legales'        => true,
        ])
            ->assertRedirect(route('registro.revisa-tu-correo', absolute: false))
            ->assertSessionHas('correo_enviado', false);
    }

    /** Un SMTP que acepta la conexion y rechaza la autenticacion. */
    private function transporteQueSiempreFalla(): void
    {
        Mail::extend('siempre_falla', fn () => new class extends AbstractTransport {
            protected function doSend(SentMessage $mensaje): void
            {
                throw new TransportException('535 5.7.8 Error: authentication failed');
            }

            public function __toString(): string
            {
                return 'siempre_falla://';
            }
        });

        config([
            'mail.default' => 'siempre_falla',
            'mail.mailers.siempre_falla' => ['transport' => 'siempre_falla'],
        ]);
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
            'acepta_legales'        => true,
            ])->assertSessionHasErrors('password');
        }

        $this->assertSame(0, User::count());
    }
}
