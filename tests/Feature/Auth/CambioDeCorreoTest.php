<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\AvisoDeCambioDeCorreo;
use App\Notifications\VerificarCorreoNuevo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Fase 4.C — el cambio de correo con el patrón seguro:
 *   - la dirección nueva se verifica antes de aplicar el cambio,
 *   - a la anterior le llega aviso,
 *   - el correo no se mueve hasta que alguien confirma la dirección nueva.
 */
class CambioDeCorreoTest extends TestCase
{
    use RefreshDatabase;

    /** Un miembro con la contraseña ya confirmada, listo para pedir el cambio. */
    private function miembroConPasswordConfirmada(): User
    {
        $miembro = User::factory()->miembro()->create([
            'email'    => 'antes@ejemplo.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($miembro);
        // La ruta va detrás de password.confirm; se simula la confirmación.
        $this->withSession(['auth.password_confirmed_at' => time()]);

        return $miembro;
    }

    /** Saca del correo on-demand el enlace real que recibiría la dirección nueva. */
    private function enlaceDe(string $correoNuevo): string
    {
        $enlace = null;

        Notification::assertSentOnDemand(
            VerificarCorreoNuevo::class,
            function ($notificacion, $canales, $notifiable) use ($correoNuevo, &$enlace) {
                if (($notifiable->routes['mail'] ?? null) !== $correoNuevo) {
                    return false;
                }
                $html = html_entity_decode($notificacion->toMail($notifiable)->render());
                preg_match('#https?://[^"\s]+/seguridad/correo/verificar/[^"\s]+#', $html, $m);
                $enlace = $m[0] ?? null;

                return true;
            }
        );

        $this->assertNotNull($enlace, 'El correo a la dirección nueva salió sin enlace.');

        return $enlace;
    }

    public function test_pedir_el_cambio_no_toca_el_correo_todavia(): void
    {
        Notification::fake();
        $miembro = $this->miembroConPasswordConfirmada();

        $this->post(route('seguridad.correo.solicitar'), ['email' => 'nuevo@ejemplo.com'])
            ->assertRedirect();

        // El correo sigue siendo el de antes; solo hay una solicitud pendiente.
        $miembro->refresh();
        $this->assertSame('antes@ejemplo.com', $miembro->email);
        $this->assertSame('nuevo@ejemplo.com', $miembro->correoPendiente());
    }

    public function test_avisa_a_la_direccion_nueva_y_a_la_anterior(): void
    {
        Notification::fake();
        $miembro = $this->miembroConPasswordConfirmada();

        $this->post(route('seguridad.correo.solicitar'), ['email' => 'nuevo@ejemplo.com']);

        // A la nueva: el enlace de confirmación.
        Notification::assertSentOnDemand(VerificarCorreoNuevo::class);
        // A la anterior: el aviso.
        Notification::assertSentTo($miembro, AvisoDeCambioDeCorreo::class);
    }

    public function test_el_enlace_de_la_direccion_nueva_aplica_el_cambio(): void
    {
        Notification::fake();
        $miembro = $this->miembroConPasswordConfirmada();

        $this->post(route('seguridad.correo.solicitar'), ['email' => 'nuevo@ejemplo.com']);
        $enlace = $this->enlaceDe('nuevo@ejemplo.com');

        $this->get($enlace)->assertRedirect();

        $miembro->refresh();
        $this->assertSame('nuevo@ejemplo.com', $miembro->email);
        $this->assertNotNull($miembro->email_verified_at);
        $this->assertNull($miembro->correoPendiente());
    }

    public function test_un_token_manipulado_no_cambia_nada(): void
    {
        Notification::fake();
        $miembro = $this->miembroConPasswordConfirmada();

        $this->post(route('seguridad.correo.solicitar'), ['email' => 'nuevo@ejemplo.com']);
        $enlace = $this->enlaceDe('nuevo@ejemplo.com');

        // Se estropea el token dentro de un enlace por lo demás bien firmado no
        // sirve; y si además se toca la firma, el middleware `signed` lo corta.
        $roto = preg_replace('#/verificar/(\d+)/[^/?]+#', '/verificar/$1/tokenfalso', $enlace);
        $this->get($roto)->assertStatus(403); // firma inválida

        $miembro->refresh();
        $this->assertSame('antes@ejemplo.com', $miembro->email);
    }

    public function test_no_se_puede_pedir_un_correo_de_otra_cuenta(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'ocupado@ejemplo.com']);
        $this->miembroConPasswordConfirmada();

        $this->post(route('seguridad.correo.solicitar'), ['email' => 'ocupado@ejemplo.com'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_cancelar_borra_la_solicitud(): void
    {
        Notification::fake();
        $miembro = $this->miembroConPasswordConfirmada();

        $this->post(route('seguridad.correo.solicitar'), ['email' => 'nuevo@ejemplo.com']);
        $this->assertSame('nuevo@ejemplo.com', $miembro->refresh()->correoPendiente());

        $this->delete(route('seguridad.correo.cancelar'))->assertRedirect();

        $this->assertNull($miembro->refresh()->correoPendiente());
    }

    public function test_pedir_el_cambio_exige_confirmar_la_contrasena(): void
    {
        Notification::fake();
        $miembro = User::factory()->miembro()->create(['email' => 'antes@ejemplo.com']);

        // Sin confirmar la contraseña: la ruta redirige a confirmarla.
        $this->actingAs($miembro)
            ->post(route('seguridad.correo.solicitar'), ['email' => 'nuevo@ejemplo.com'])
            ->assertRedirect(route('password.confirm'));

        Notification::assertNothingSent();
        $this->assertNull($miembro->refresh()->correoPendiente());
    }

    public function test_el_perfil_de_breeze_ya_no_cambia_el_correo(): void
    {
        $miembro = User::factory()->miembro()->create([
            'email'             => 'antes@ejemplo.com',
            'email_verified_at' => now(),
        ]);

        // Aunque se cuele un `email` en el PATCH del perfil, se ignora.
        $this->actingAs($miembro)
            ->patch(route('profile.update'), [
                'name'  => 'Nombre Nuevo',
                'email' => 'colado@ejemplo.com',
            ])
            ->assertRedirect();

        $miembro->refresh();
        $this->assertSame('antes@ejemplo.com', $miembro->email);
        $this->assertSame('Nombre Nuevo', $miembro->name);
        $this->assertNotNull($miembro->email_verified_at); // sigue verificado
    }
}
