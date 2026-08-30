<?php

namespace Tests\Feature\Auth;

use App\Models\CodigoRecuperacion;
use App\Models\DispositivoConfiable;
use App\Models\EnlaceMagico;
use App\Models\IdentidadSocial;
use App\Models\User;
use App\Support\DosFactores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Fase D — segundo factor.
 */
class SegundoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'MiClaveNodico2026';

    private function servicio(): DosFactores
    {
        return app(DosFactores::class);
    }

    /** Una cuenta con el segundo factor ya activo, y su secreto. */
    private function conDosFactores(array $atributos = []): array
    {
        $usuario = User::factory()->miembro()->create($atributos);
        $secreto = $this->servicio()->generarSecreto();

        $usuario->forceFill([
            'dos_factores_secreto'       => $secreto,
            'dos_factores_confirmado_en' => now(),
        ])->save();

        return [$usuario->fresh(), $secreto];
    }

    private function codigoValido(string $secreto): string
    {
        return (new Google2FA())->getCurrentOtp($secreto);
    }

    /**
     * La cookie del dispositivo de confianza se manda con `withCookie` y el
     * valor **en claro**.
     *
     * Laravel ya la cifra y le pone el prefijo que `EncryptCookies` verifica
     * (`prepareCookiesForRequest`), asi que cifrarla aqui la deja con dos capas
     * y el middleware entrega una cadena cifrada en vez del token.
     * `withUnencryptedCookie` falla por lo contrario: la deja sin cifrar y el
     * middleware la descarta. Con las dos, la peticion llega sin cookie util y
     * la prueba pasaria por el motivo equivocado —siempre al desafio— sin
     * comprobar nada.
     */

    // ── El desafío corta el acceso ───────────────────────────────────────────

    public function test_con_segundo_factor_la_contrasena_no_basta(): void
    {
        [$usuario] = $this->conDosFactores(['email' => 'quien@example.com']);

        $this->post('/login', ['email' => 'quien@example.com', 'password' => 'password'])
            ->assertRedirect(route('dos-factores.desafio', absolute: false));

        // Lo esencial: entre la contraseña y el código **no hay sesión**.
        $this->assertGuest();
    }

    public function test_el_desafio_no_se_puede_abrir_sin_haber_pasado_la_contrasena(): void
    {
        $this->get('/dos-factores/desafio')->assertRedirect(route('login', absolute: false));
    }

    public function test_el_codigo_correcto_completa_el_acceso(): void
    {
        [$usuario, $secreto] = $this->conDosFactores(['email' => 'quien@example.com']);

        $this->post('/login', ['email' => 'quien@example.com', 'password' => 'password']);

        $this->post('/dos-factores/desafio', ['codigo' => $this->codigoValido($secreto)])
            ->assertRedirect(route('portal.dashboard', absolute: false));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_un_codigo_incorrecto_no_abre_sesion(): void
    {
        $this->conDosFactores(['email' => 'quien@example.com']);

        $this->post('/login', ['email' => 'quien@example.com', 'password' => 'password']);

        $this->post('/dos-factores/desafio', ['codigo' => '000000'])
            ->assertSessionHasErrors('codigo');

        $this->assertGuest();
    }

    // ── No se puede rodear por ningún camino ─────────────────────────────────

    /**
     * La prueba que de verdad importa. Un segundo factor que se rodea entrando
     * con un enlace mágico no es un segundo factor: bastaría con tener acceso
     * al buzón para saltárselo.
     */
    public function test_el_enlace_magico_no_rodea_el_segundo_factor(): void
    {
        config(['nodico.acceso.enlace_magico' => true]);

        $this->conDosFactores(['email' => 'quien@example.com']);

        $this->post('/enlace-magico', ['email' => 'quien@example.com']);

        $ultimo = app('mailer')->getSymfonyTransport()->messages()->last();
        preg_match('#/enlace-magico/([A-Za-z0-9]{48})#', (string) $ultimo->getOriginalMessage()->getHtmlBody(), $c);

        $this->get(route('enlace-magico.entrar', ['token' => $c[1]]))
            ->assertRedirect(route('dos-factores.desafio', absolute: false));

        $this->assertGuest();
    }

    /** Y entrar con Google tampoco. */
    public function test_google_no_rodea_el_segundo_factor(): void
    {
        config([
            'nodico.acceso.google' => true,
            'services.google' => ['client_id' => 'x', 'client_secret' => 'x', 'redirect' => 'https://x/y'],
        ]);

        [$usuario] = $this->conDosFactores(['email' => 'quien@example.com']);

        IdentidadSocial::create([
            'user_id' => $usuario->id, 'proveedor' => 'google', 'proveedor_id' => 'google-77',
        ]);

        $externo = Mockery::mock('Laravel\Socialite\Two\User');
        $externo->shouldReceive('getId')->andReturn('google-77');
        $externo->shouldReceive('getEmail')->andReturn('quien@example.com');
        $externo->shouldReceive('getName')->andReturn('Quien Sea');
        $externo->shouldReceive('getAvatar')->andReturn(null);
        $externo->user = ['email_verified' => true];

        $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $driver->shouldReceive('enablePkce')->andReturnSelf();
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($externo);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get('/acceso/google/retorno')
            ->assertRedirect(route('dos-factores.desafio', absolute: false));

        $this->assertGuest();
    }

    // ── Códigos de recuperación ──────────────────────────────────────────────

    public function test_un_codigo_de_recuperacion_sirve_una_sola_vez(): void
    {
        [$usuario] = $this->conDosFactores(['email' => 'quien@example.com']);

        $codigos = $this->servicio()->generarCodigosDeRecuperacion($usuario);
        $codigo  = $codigos[0];

        $this->post('/login', ['email' => 'quien@example.com', 'password' => 'password']);
        $this->post('/dos-factores/desafio', ['codigo_recuperacion' => $codigo])
            ->assertRedirect(route('portal.dashboard', absolute: false));

        $this->assertAuthenticatedAs($usuario);

        // Segundo intento con el mismo código: ya no sirve.
        $this->post('/logout');
        $this->post('/login', ['email' => 'quien@example.com', 'password' => 'password']);

        $this->post('/dos-factores/desafio', ['codigo_recuperacion' => $codigo])
            ->assertSessionHasErrors('codigo_recuperacion');

        $this->assertGuest();
    }

    /** Se guardan hasheados: en la base no está el código, solo su huella. */
    public function test_los_codigos_de_recuperacion_no_se_guardan_en_claro(): void
    {
        [$usuario] = $this->conDosFactores();

        $codigos = $this->servicio()->generarCodigosDeRecuperacion($usuario);

        foreach ($codigos as $codigo) {
            $this->assertDatabaseMissing('codigos_recuperacion', ['hash' => $codigo]);
            $this->assertDatabaseHas('codigos_recuperacion', [
                'hash' => CodigoRecuperacion::hashear($codigo),
            ]);
        }
    }

    public function test_regenerar_los_codigos_invalida_los_anteriores(): void
    {
        [$usuario] = $this->conDosFactores();

        $viejos = $this->servicio()->generarCodigosDeRecuperacion($usuario);
        $this->servicio()->generarCodigosDeRecuperacion($usuario);

        $this->assertFalse(
            $this->servicio()->consumirCodigoDeRecuperacion($usuario->fresh(), $viejos[0]),
        );
    }

    // ── Alta y baja ──────────────────────────────────────────────────────────

    /**
     * Un secreto sin confirmar no activa nada. Si se activara, quien escaneara
     * mal el QR quedaria fuera de su propia cuenta sin remedio.
     */
    public function test_el_secreto_no_activa_el_segundo_factor_hasta_confirmarlo(): void
    {
        $usuario = User::factory()->miembro()->create();

        $this->actingAs($usuario)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/dos-factores')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Auth/TwoFactorSetup')->has('qr'));

        $this->assertFalse($usuario->fresh()->tieneDosFactores());
    }

    public function test_un_codigo_equivocado_no_activa_el_segundo_factor(): void
    {
        $usuario = User::factory()->miembro()->create();

        $this->actingAs($usuario)->withSession(['auth.password_confirmed_at' => time()])->get('/dos-factores');

        $this->actingAs($usuario)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/dos-factores', ['codigo' => '000000'])
            ->assertSessionHasErrors('codigo');

        $this->assertFalse($usuario->fresh()->tieneDosFactores());
    }

    public function test_activar_y_desactivar_exige_confirmar_la_contrasena(): void
    {
        $usuario = User::factory()->miembro()->create();

        $this->actingAs($usuario)
            ->get('/dos-factores')
            ->assertRedirect(route('password.confirm', absolute: false));

        $this->actingAs($usuario)
            ->delete(route('dos-factores.destruir'))
            ->assertRedirect(route('password.confirm', absolute: false));
    }

    public function test_desactivarlo_borra_codigos_y_dispositivos(): void
    {
        [$usuario] = $this->conDosFactores();

        $this->servicio()->generarCodigosDeRecuperacion($usuario);

        DispositivoConfiable::create([
            'user_id'   => $usuario->id,
            'token'     => DispositivoConfiable::hashear('lo-que-sea'),
            'expira_en' => now()->addDays(30),
        ]);

        $this->actingAs($usuario)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('dos-factores.destruir'));

        $this->assertFalse($usuario->fresh()->tieneDosFactores());
        $this->assertDatabaseCount('codigos_recuperacion', 0);
        $this->assertDatabaseCount('dispositivos_confiables', 0);
    }

    /** El interruptor por rol, listo para cuando el IYEM lo pida. */
    public function test_un_rol_obligado_no_puede_desactivar_el_segundo_factor(): void
    {
        config(['nodico.dos_factores.obligatorio_para' => ['admin']]);

        $admin   = User::factory()->admin()->create();
        $secreto = $this->servicio()->generarSecreto();
        $admin->forceFill([
            'dos_factores_secreto'       => $secreto,
            'dos_factores_confirmado_en' => now(),
        ])->save();

        $this->actingAs($admin->fresh())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('dos-factores.destruir'))
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->tieneDosFactores());
    }

    // ── Dispositivos de confianza ────────────────────────────────────────────

    public function test_un_dispositivo_de_confianza_se_salta_el_desafio(): void
    {
        [$usuario] = $this->conDosFactores(['email' => 'quien@example.com']);

        DispositivoConfiable::create([
            'user_id'   => $usuario->id,
            'token'     => DispositivoConfiable::hashear('token-de-confianza'),
            'expira_en' => now()->addDays(30),
        ]);

        $this->withCookie(DosFactores::COOKIE, 'token-de-confianza')
            ->post('/login', ['email' => 'quien@example.com', 'password' => 'password'])
            ->assertRedirect(route('portal.dashboard', absolute: false));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_una_confianza_caducada_vuelve_a_pedir_el_codigo(): void
    {
        [$usuario] = $this->conDosFactores(['email' => 'quien@example.com']);

        DispositivoConfiable::create([
            'user_id'   => $usuario->id,
            'token'     => DispositivoConfiable::hashear('token-viejo'),
            'expira_en' => now()->subDay(),
        ]);

        $this->withCookie(DosFactores::COOKIE, 'token-viejo')
            ->post('/login', ['email' => 'quien@example.com', 'password' => 'password'])
            ->assertRedirect(route('dos-factores.desafio', absolute: false));

        $this->assertGuest();
    }

    public function test_nadie_puede_quitar_la_confianza_de_otra_persona(): void
    {
        $mia   = User::factory()->miembro()->create();
        $ajena = User::factory()->miembro()->create();

        $equipo = DispositivoConfiable::create([
            'user_id'   => $ajena->id,
            'token'     => DispositivoConfiable::hashear('ajeno'),
            'expira_en' => now()->addDays(30),
        ]);

        $this->actingAs($mia)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('seguridad.olvidar-dispositivo', ['dispositivo' => $equipo->id]))
            ->assertStatus(403);

        $this->assertDatabaseHas('dispositivos_confiables', ['id' => $equipo->id]);
    }
}
