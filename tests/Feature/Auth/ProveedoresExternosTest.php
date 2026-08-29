<?php

namespace Tests\Feature\Auth;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\EnlaceMagico;
use App\Models\IdentidadSocial;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery;
use Tests\TestCase;

/**
 * Fase C — proveedores externos y enlace mágico.
 */
class ProveedoresExternosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'nodico.acceso.google'        => true,
            'nodico.acceso.enlace_magico' => true,
            'services.google' => [
                'client_id'     => 'de-prueba',
                'client_secret' => 'de-prueba',
                'redirect'      => 'https://prueba.nodico.com.mx/acceso/google/retorno',
            ],
        ]);
    }

    /** Un usuario tal como lo entrega Socialite. */
    private function usuarioDeGoogle(string $id, string $correo, bool $verificado = true, string $nombre = 'Persona Google'): object
    {
        $externo = Mockery::mock('Laravel\Socialite\Two\User');
        $externo->shouldReceive('getId')->andReturn($id);
        $externo->shouldReceive('getEmail')->andReturn($correo);
        $externo->shouldReceive('getName')->andReturn($nombre);
        $externo->shouldReceive('getAvatar')->andReturn('https://ejemplo/avatar.png');
        $externo->user = ['email_verified' => $verificado];

        return $externo;
    }

    private function fingirGoogle(object $externo): void
    {
        $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $driver->shouldReceive('enablePkce')->andReturnSelf();
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($externo);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    // ── Interruptores ────────────────────────────────────────────────────────

    /**
     * Con el proveedor apagado la ruta **no existe**: 404, no 403. Esconder el
     * botón en el front no es control de acceso.
     */
    public function test_con_google_apagado_la_ruta_responde_404(): void
    {
        config(['nodico.acceso.google' => false]);

        $this->get('/acceso/google')->assertNotFound();
        $this->get('/acceso/google/retorno')->assertNotFound();
    }

    public function test_un_proveedor_que_no_esta_en_la_lista_blanca_no_existe(): void
    {
        $this->get('/acceso/facebook')->assertNotFound();
        $this->get('/acceso/apple')->assertNotFound();
    }

    public function test_con_el_enlace_magico_apagado_la_ruta_responde_404(): void
    {
        config(['nodico.acceso.enlace_magico' => false]);

        $this->post('/enlace-magico', ['email' => 'quien@example.com'])->assertNotFound();
    }

    // ── OAuth ────────────────────────────────────────────────────────────────

    /**
     * `state` es la única defensa contra CSRF en este flujo, y su fallo es el
     * error más común. Socialite lanza `InvalidStateException`; el usuario
     * tiene que acabar en el login, no en una traza.
     */
    public function test_un_state_invalido_no_abre_sesion(): void
    {
        $driver = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $driver->shouldReceive('enablePkce')->andReturnSelf();
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('user')->andThrow(new InvalidStateException());

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get('/acceso/google/retorno')
            ->assertRedirect(route('login', absolute: false));

        $this->assertGuest();
    }

    public function test_cancelar_en_google_termina_en_el_login_con_mensaje(): void
    {
        $this->get('/acceso/google/retorno?error=access_denied')
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('status');

        $this->assertGuest();
    }

    public function test_google_crea_la_cuenta_verificada_y_sin_contrasena(): void
    {
        $this->fingirGoogle($this->usuarioDeGoogle('google-1', 'nueva@example.com'));

        $this->get('/acceso/google/retorno')
            ->assertRedirect(route('portal.dashboard', absolute: false));

        $usuario = User::where('email', 'nueva@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($usuario);
        $this->assertTrue($usuario->hasVerifiedEmail(), 'Google ya validó ese correo.');
        $this->assertFalse($usuario->tieneContrasena());
        $this->assertSame(RolUsuario::Miembro, $usuario->rol);
        $this->assertSame(EstadoCuenta::Pendiente, $usuario->estado);
    }

    public function test_google_se_vincula_a_la_cuenta_existente_en_vez_de_duplicarla(): void
    {
        $existente = User::factory()->miembro()->create(['email' => 'ya@example.com']);
        $cuantos   = User::count();

        $this->fingirGoogle($this->usuarioDeGoogle('google-2', 'ya@example.com'));

        $this->get('/acceso/google/retorno');

        $this->assertAuthenticatedAs($existente);
        $this->assertSame($cuantos, User::count(), 'No debe crearse una cuenta duplicada.');
        $this->assertDatabaseHas('identidades_sociales', [
            'user_id'      => $existente->id,
            'proveedor'    => 'google',
            'proveedor_id' => 'google-2',
        ]);
    }

    /**
     * Sin esta comprobación, registrar una cuenta de Google con el correo de
     * otra persona bastaría para quedarse con su cuenta de Nódico.
     */
    public function test_google_no_se_vincula_si_el_correo_no_esta_verificado(): void
    {
        $existente = User::factory()->miembro()->create(['email' => 'ya@example.com']);

        $this->fingirGoogle($this->usuarioDeGoogle('google-3', 'ya@example.com', verificado: false));

        $this->get('/acceso/google/retorno')
            ->assertRedirect(route('login', absolute: false));

        $this->assertGuest();
        $this->assertDatabaseCount('identidades_sociales', 0);
    }

    /** La base lo impide, no solo el código. */
    public function test_dos_cuentas_no_pueden_vincular_la_misma_identidad_de_google(): void
    {
        $una  = User::factory()->miembro()->create();
        $otra = User::factory()->miembro()->create();

        IdentidadSocial::create([
            'user_id' => $una->id, 'proveedor' => 'google', 'proveedor_id' => 'mismo-id',
        ]);

        $this->expectException(QueryException::class);

        IdentidadSocial::create([
            'user_id' => $otra->id, 'proveedor' => 'google', 'proveedor_id' => 'mismo-id',
        ]);
    }

    // ── Desvinculación ───────────────────────────────────────────────────────

    public function test_no_se_puede_desvincular_el_ultimo_metodo_de_acceso(): void
    {
        $usuario = User::factory()->miembro()->create();
        $usuario->forceFill(['password' => null])->save();

        $identidad = IdentidadSocial::create([
            'user_id' => $usuario->id, 'proveedor' => 'google', 'proveedor_id' => 'google-9',
        ]);

        $this->actingAs($usuario->fresh())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('seguridad.desvincular', ['identidad' => $identidad->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('identidades_sociales', ['id' => $identidad->id]);
    }

    public function test_se_puede_desvincular_si_queda_otra_forma_de_entrar(): void
    {
        $usuario = User::factory()->miembro()->create(); // con contraseña

        $identidad = IdentidadSocial::create([
            'user_id' => $usuario->id, 'proveedor' => 'google', 'proveedor_id' => 'google-10',
        ]);

        $this->actingAs($usuario)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('seguridad.desvincular', ['identidad' => $identidad->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('identidades_sociales', ['id' => $identidad->id]);
    }

    public function test_nadie_puede_desvincular_la_identidad_de_otra_persona(): void
    {
        $mia   = User::factory()->miembro()->create();
        $ajena = User::factory()->miembro()->create();

        $identidad = IdentidadSocial::create([
            'user_id' => $ajena->id, 'proveedor' => 'google', 'proveedor_id' => 'google-11',
        ]);

        $this->actingAs($mia)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('seguridad.desvincular', ['identidad' => $identidad->id]))
            ->assertStatus(403);

        $this->assertDatabaseHas('identidades_sociales', ['id' => $identidad->id]);
    }

    // ── Enlace mágico ────────────────────────────────────────────────────────

    public function test_el_enlace_magico_responde_igual_exista_o_no_la_cuenta(): void
    {
        Notification::fake();
        User::factory()->miembro()->create(['email' => 'existe@example.com']);

        foreach (['existe@example.com', 'nadie@example.com'] as $correo) {
            $this->post('/enlace-magico', ['email' => $correo])
                ->assertRedirect(route('enlace-magico.enviado', absolute: false))
                ->assertSessionHasNoErrors();
        }
    }

    public function test_el_enlace_magico_abre_sesion_una_sola_vez(): void
    {
        $usuario = User::factory()->miembro()->create(['email' => 'quien@example.com']);

        $this->post('/enlace-magico', ['email' => 'quien@example.com']);

        $enlace = EnlaceMagico::where('user_id', $usuario->id)->firstOrFail();
        $token  = $this->tokenDelUltimoEnlace();

        $this->get(route('enlace-magico.entrar', ['token' => $token]))
            ->assertRedirect(route('portal.dashboard', absolute: false));

        $this->assertAuthenticatedAs($usuario);
        $this->assertNotNull($enlace->fresh()->usado_en);

        // Segundo intento con el mismo enlace: ya no sirve.
        $this->post('/logout');

        $this->get(route('enlace-magico.entrar', ['token' => $token]))
            ->assertRedirect(route('login', absolute: false));

        $this->assertGuest();
    }

    public function test_el_enlace_magico_no_sirve_desde_otro_navegador(): void
    {
        User::factory()->miembro()->create(['email' => 'quien@example.com']);

        $this->post('/enlace-magico', ['email' => 'quien@example.com']);
        $token = $this->tokenDelUltimoEnlace();

        // Otro navegador = otra sesión, sin el secreto que quedó en la primera.
        $this->flushSession();

        $this->get(route('enlace-magico.entrar', ['token' => $token]))
            ->assertRedirect(route('login', absolute: false));

        $this->assertGuest();
    }

    public function test_el_enlace_magico_caduca_a_los_quince_minutos(): void
    {
        User::factory()->miembro()->create(['email' => 'quien@example.com']);

        $this->post('/enlace-magico', ['email' => 'quien@example.com']);
        $token = $this->tokenDelUltimoEnlace();

        $this->travel(EnlaceMagico::MINUTOS_DE_VIDA + 1)->minutes();

        $this->get(route('enlace-magico.entrar', ['token' => $token]))
            ->assertRedirect(route('login', absolute: false));

        $this->assertGuest();
    }

    /** Pedir uno nuevo invalida el anterior. */
    public function test_pedir_otro_enlace_invalida_el_anterior(): void
    {
        User::factory()->miembro()->create(['email' => 'quien@example.com']);

        $this->post('/enlace-magico', ['email' => 'quien@example.com']);
        $primero = $this->tokenDelUltimoEnlace();

        $this->post('/enlace-magico', ['email' => 'quien@example.com']);

        $this->get(route('enlace-magico.entrar', ['token' => $primero]))
            ->assertRedirect(route('login', absolute: false));

        $this->assertGuest();
    }

    /**
     * El token en claro solo existe en el correo, así que se saca del HTML
     * enviado: es también la prueba de que la plantilla compila y de que el
     * enlace del buzón es el que el servidor acepta.
     */
    private function tokenDelUltimoEnlace(): string
    {
        // El transporte `array` de las pruebas guarda los mensajes en una
        // coleccion, no en un array: `end()` no sirve aqui.
        $ultimo = app('mailer')->getSymfonyTransport()->messages()->last();

        $this->assertNotNull($ultimo, 'No se envio ningun correo.');

        $cuerpo = $ultimo->getOriginalMessage()->getHtmlBody();

        preg_match('#/enlace-magico/([A-Za-z0-9]{48})#', (string) $cuerpo, $coincidencias);

        $this->assertNotEmpty($coincidencias, 'El correo salió sin enlace utilizable.');

        return $coincidencias[1];
    }
}
