<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** Devuelve el enlace real que recibiria la persona en su correo. */
    private function enlaceDeVerificacion(User $usuario): string
    {
        Notification::fake();

        $usuario->sendEmailVerificationNotification();

        $enlace = null;

        Notification::assertSentTo($usuario, VerifyEmail::class, function ($notificacion) use ($usuario, &$enlace) {
            // Se saca del HTML que de verdad se envia, no se reconstruye: asi
            // esto tambien comprueba que la plantilla de Nodico compila y que
            // el enlace que llega al buzon es el que el servidor acepta.
            $html = html_entity_decode($notificacion->toMail($usuario)->render());

            preg_match('#https?://[^"\s]+/verify-email/[^"\s]+#', $html, $coincidencias);

            $enlace = $coincidencias[0] ?? null;

            return true;
        });

        $this->assertNotNull($enlace, 'El correo de verificacion salio sin enlace.');

        return $enlace;
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $usuario = User::factory()->unverified()->create();

        $this->actingAs($usuario)->get('/verify-email')->assertStatus(200);
    }

    public function test_el_correo_se_verifica_y_la_persona_llega_a_su_portal(): void
    {
        $usuario = User::factory()->unverified()->miembro()->create();
        $enlace  = $this->enlaceDeVerificacion($usuario);

        Event::fake();

        $respuesta = $this->actingAs($usuario)->get($enlace);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($usuario->fresh()->hasVerifiedEmail());

        // Antes iba a `dashboard` para todo el mundo, asi que un miembro
        // aterrizaba en el panel operativo y de ahi salia rebotado (A.1).
        $respuesta->assertRedirect(route('portal.dashboard', absolute: false));
    }

    public function test_el_equipo_de_nodico_verifica_y_llega_a_su_panel(): void
    {
        $admin  = User::factory()->unverified()->admin()->create();
        $enlace = $this->enlaceDeVerificacion($admin);

        $this->actingAs($admin)
            ->get($enlace)
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $usuario = User::factory()->unverified()->create();

        $enlaceFalso = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $usuario->id, 'hash' => sha1('otro-correo')]
        );

        $this->actingAs($usuario)->get($enlaceFalso);

        $this->assertFalse($usuario->fresh()->hasVerifiedEmail());
    }

    /**
     * A.2 — El enlace es de un solo uso.
     *
     * Se simula el caso que importa: alguien captura el enlace, la persona
     * verifica, y despues su correo vuelve a quedar sin verificar (por ejemplo
     * porque lo cambio desde su perfil). El enlace viejo no puede servir para
     * reverificar la direccion nueva.
     */
    public function test_el_enlace_de_verificacion_no_sirve_dos_veces(): void
    {
        $usuario = User::factory()->unverified()->create();
        $enlace  = $this->enlaceDeVerificacion($usuario);

        $this->actingAs($usuario)->get($enlace);
        $this->assertTrue($usuario->fresh()->hasVerifiedEmail());

        // El correo cambia: la cuenta vuelve a estar sin verificar.
        $usuario->fresh()->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($usuario->fresh())->get($enlace)->assertStatus(403);
        $this->assertFalse($usuario->fresh()->hasVerifiedEmail());
    }

    /** Reenviar genera un enlace nuevo e invalida el anterior. */
    public function test_reenviar_invalida_el_enlace_anterior(): void
    {
        $usuario = User::factory()->unverified()->create();
        $primero = $this->enlaceDeVerificacion($usuario);

        Notification::fake();
        $this->actingAs($usuario->fresh())->post('/email/verification-notification');

        $this->actingAs($usuario->fresh())->get($primero)->assertStatus(403);
        $this->assertFalse($usuario->fresh()->hasVerifiedEmail());
    }

    public function test_el_enlace_caduca_a_los_sesenta_minutos(): void
    {
        $usuario = User::factory()->unverified()->create();
        $enlace  = $this->enlaceDeVerificacion($usuario);

        $this->travel(61)->minutes();

        $this->actingAs($usuario)->get($enlace)->assertStatus(403);
        $this->assertFalse($usuario->fresh()->hasVerifiedEmail());
    }
}
