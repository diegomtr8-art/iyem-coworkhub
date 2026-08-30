<?php

namespace Tests\Feature\Auth;

use App\Enums\EventoAuth;
use App\Models\EventoAutenticacion;
use App\Models\User;
use App\Notifications\AccesoBloqueado;
use App\Notifications\CuentaYaExiste;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Fase B — seguridad del núcleo.
 */
class SeguridadDelAccesoTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'MiClaveNodico2026';

    // ── Enumeración de cuentas ───────────────────────────────────────────────

    public function test_el_login_dice_lo_mismo_exista_o_no_la_cuenta(): void
    {
        User::factory()->create(['email' => 'existe@example.com']);

        $conCuenta = $this->post('/login', ['email' => 'existe@example.com', 'password' => 'equivocada']);
        $sinCuenta = $this->post('/login', ['email' => 'nadie@example.com', 'password' => 'equivocada']);

        $conCuenta->assertSessionHasErrors(['email' => trans('auth.failed')]);
        $sinCuenta->assertSessionHasErrors(['email' => trans('auth.failed')]);
        $this->assertGuest();
    }

    public function test_la_recuperacion_dice_lo_mismo_exista_o_no_la_cuenta(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'existe@example.com']);

        foreach (['existe@example.com', 'nadie@example.com'] as $correo) {
            $this->post('/forgot-password', ['email' => $correo])
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', trans('passwords.sent'));
        }
    }

    /**
     * El caso que decidió Diego: registrarse con un correo que ya tiene cuenta
     * responde exactamente igual que un alta nueva, y la aclaración va por
     * correo.
     */
    public function test_registrarse_con_un_correo_repetido_no_lo_delata(): void
    {
        Notification::fake();

        $existente = User::factory()->create(['email' => 'ya@example.com', 'name' => 'Ana Ya']);
        $cuantos   = User::count();

        $respuesta = $this->post('/register', [
            'name'                  => 'Otra Persona',
            'email'                 => 'ya@example.com',
            'password'              => self::CLAVE,
            'password_confirmation' => self::CLAVE,
            'acepta_legales'        => true,
        ]);

        // Mismo destino que un alta nueva y ninguna sesión abierta.
        $respuesta->assertRedirect(route('registro.revisa-tu-correo', absolute: false));
        $respuesta->assertSessionHasNoErrors();
        $this->assertGuest();

        // No se creó nada ni se tocó la cuenta existente.
        $this->assertSame($cuantos, User::count());
        $this->assertSame('Ana Ya', $existente->fresh()->name);

        Notification::assertSentTo($existente, CuentaYaExiste::class);
    }

    public function test_la_pantalla_es_la_misma_para_el_alta_nueva_y_para_el_correo_repetido(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'ya@example.com']);

        $datos = [
            'name'                  => 'Quien Sea',
            'password'              => self::CLAVE,
            'password_confirmation' => self::CLAVE,
            'acepta_legales'        => true,
        ];

        $this->post('/register', $datos + ['email' => 'nueva@example.com']);
        $nueva = $this->get(route('registro.revisa-tu-correo'));

        $this->post('/register', $datos + ['email' => 'ya@example.com']);
        $repetida = $this->get(route('registro.revisa-tu-correo'));

        foreach ([$nueva, $repetida] as $respuesta) {
            $respuesta->assertOk()->assertInertia(
                fn ($pagina) => $pagina->component('Auth/RevisaTuCorreo')
            );
        }
    }

    // ── Límite de intentos ───────────────────────────────────────────────────

    /**
     * La regresión de BE-02 aplicada al acceso: **todo Nódico sale por la misma
     * IP**. Que una persona falle cinco veces no puede dejar fuera al resto de
     * la sala.
     */
    public function test_el_limite_por_cuenta_no_bloquea_a_otras_cuentas_de_la_misma_ip(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'martillada@example.com']);
        User::factory()->create(['email' => 'inocente@example.com']);

        for ($intento = 0; $intento < 6; $intento++) {
            $this->post('/login', ['email' => 'martillada@example.com', 'password' => 'mal']);
        }

        // La cuenta martillada está en espera aunque acierte la contraseña.
        $this->post('/login', ['email' => 'martillada@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // Desde la misma IP, otra persona entra sin estorbo.
        $this->post('/login', ['email' => 'inocente@example.com', 'password' => 'password']);
        $this->assertAuthenticated();
    }

    public function test_el_mensaje_de_espera_dice_cuanto_falta(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'martillada@example.com']);

        for ($intento = 0; $intento < 6; $intento++) {
            $respuesta = $this->post('/login', ['email' => 'martillada@example.com', 'password' => 'mal']);
        }

        $errores = session('errors')->get('email');

        $this->assertStringContainsString('Vuelve a intentarlo en', $errores[0]);
    }

    public function test_el_bloqueo_queda_en_bitacora_y_avisa_al_titular(): void
    {
        Notification::fake();
        $titular = User::factory()->create(['email' => 'martillada@example.com']);

        for ($intento = 0; $intento < 6; $intento++) {
            $this->post('/login', ['email' => 'martillada@example.com', 'password' => 'mal']);
        }

        Notification::assertSentTo($titular, AccesoBloqueado::class);

        $this->assertDatabaseHas('eventos_auth', [
            'tipo'   => EventoAuth::Bloqueo->value,
            'correo' => 'martillada@example.com',
            'exito'  => false,
        ]);
    }

    /** Diez intentos seguidos no deben mandar diez correos al titular. */
    public function test_el_aviso_de_bloqueo_no_se_repite_en_cada_intento(): void
    {
        Notification::fake();
        $titular = User::factory()->create(['email' => 'martillada@example.com']);

        for ($intento = 0; $intento < 10; $intento++) {
            $this->post('/login', ['email' => 'martillada@example.com', 'password' => 'mal']);
        }

        Notification::assertSentTimes(AccesoBloqueado::class, 1);
    }

    // ── Bitácora ─────────────────────────────────────────────────────────────

    public function test_la_bitacora_anota_ingresos_y_fallos(): void
    {
        $usuario = User::factory()->create(['email' => 'quien@example.com']);

        $this->post('/login', ['email' => 'quien@example.com', 'password' => 'mal']);
        $this->post('/login', ['email' => 'quien@example.com', 'password' => 'password']);

        $this->assertDatabaseHas('eventos_auth', [
            'tipo'    => EventoAuth::IngresoFallido->value,
            'correo'  => 'quien@example.com',
            'exito'   => false,
        ]);

        $this->assertDatabaseHas('eventos_auth', [
            'tipo'    => EventoAuth::IngresoCorrecto->value,
            'user_id' => $usuario->id,
            'exito'   => true,
        ]);
    }

    /** Un intento contra un correo sin cuenta también queda: delata un barrido. */
    public function test_la_bitacora_anota_los_intentos_contra_correos_inexistentes(): void
    {
        $this->post('/login', ['email' => 'fantasma@example.com', 'password' => 'mal']);

        $this->assertDatabaseHas('eventos_auth', [
            'tipo'    => EventoAuth::IngresoFallido->value,
            'correo'  => 'fantasma@example.com',
            'user_id' => null,
        ]);
    }

    public function test_cada_quien_ve_su_bitacora_y_solo_la_suya(): void
    {
        $mia   = User::factory()->miembro()->create();
        $ajena = User::factory()->miembro()->create();

        EventoAutenticacion::registrar(EventoAuth::IngresoCorrecto, $mia);
        EventoAutenticacion::registrar(EventoAuth::IngresoCorrecto, $ajena);

        $this->actingAs($mia)
            ->get('/seguridad')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('Seguridad/Index')
                ->has('eventos', 1));
    }

    public function test_la_bitacora_completa_es_solo_para_administracion(): void
    {
        $this->actingAs(User::factory()->staff()->create())->get('/bitacora')->assertStatus(403);
        $this->actingAs(User::factory()->miembro()->create())->get('/bitacora')->assertStatus(403);
        $this->actingAs(User::factory()->admin()->create())->get('/bitacora')->assertOk();
    }

    // ── Sesiones ─────────────────────────────────────────────────────────────

    /**
     * Si las sesiones abiertas sobreviven al cambio de contraseña, quien entró
     * con la vieja sigue dentro y la persona cree que ya lo echó.
     */
    public function test_cambiar_la_contrasena_cierra_las_demas_sesiones(): void
    {
        config(['session.driver' => 'database']);

        $usuario = User::factory()->create();

        foreach (['sesion-vieja-1', 'sesion-vieja-2'] as $id) {
            DB::table('sessions')->insert([
                'id'            => $id,
                'user_id'       => $usuario->id,
                'ip_address'    => '10.0.0.9',
                'user_agent'    => 'Mozilla/5.0 (iPhone)',
                'payload'       => '',
                'last_activity' => now()->getTimestamp(),
            ]);
        }

        $this->actingAs($usuario)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from('/profile')
            ->put('/password', [
                'current_password'      => 'password',
                'password'              => self::CLAVE,
                'password_confirmation' => self::CLAVE,
            'acepta_legales'        => true,
            ])
            ->assertSessionHasNoErrors();

        // Las dos viejas desaparecen. Queda una fila: la sesion en curso, que el
        // driver `database` vuelve a escribir al terminar la peticion — es la de
        // quien acaba de cambiar la contrasena, y esa no debe cerrarse.
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-vieja-1']);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-vieja-2']);

        $this->assertDatabaseHas('eventos_auth', [
            'tipo'    => EventoAuth::CambioContrasena->value,
            'user_id' => $usuario->id,
        ]);
    }

    public function test_nadie_puede_cerrar_la_sesion_de_otra_persona(): void
    {
        config(['session.driver' => 'database']);

        $mia   = User::factory()->create();
        $ajena = User::factory()->create();

        DB::table('sessions')->insert([
            'id'            => 'sesion-ajena',
            'user_id'       => $ajena->id,
            'ip_address'    => '10.0.0.9',
            'user_agent'    => 'Mozilla/5.0',
            'payload'       => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        // Se manda la referencia legitima de la sesion ajena: el caso fuerte,
        // no una cadena inventada que fallaria por no existir.
        $referenciaAjena = hash_hmac('sha256', 'sesion-ajena', (string) config('app.key'));

        $this->actingAs($mia)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('seguridad.cerrar-una', ['sesion' => $referenciaAjena]));

        $this->assertDatabaseHas('sessions', ['id' => 'sesion-ajena']);
    }

    /**
     * El identificador de sesion es una credencial: quien lo tenga **es** esa
     * sesion. Se enviaba en claro a la pantalla y viajaba dentro de la URL del
     * boton de cerrar, asi que quedaba en el historial del navegador y en el
     * log de accesos del servidor. Ahora solo sale una referencia opaca.
     */
    public function test_el_identificador_de_sesion_nunca_llega_al_navegador(): void
    {
        config(['session.driver' => 'database']);

        $usuario = User::factory()->miembro()->create();

        DB::table('sessions')->insert([
            'id'            => 'identificador-secreto-de-sesion',
            'user_id'       => $usuario->id,
            'ip_address'    => '10.0.0.9',
            'user_agent'    => 'Mozilla/5.0',
            'payload'       => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $respuesta = $this->actingAs($usuario)->get('/seguridad');

        $respuesta->assertOk();
        $respuesta->assertDontSee('identificador-secreto-de-sesion');
    }

    public function test_cerrar_otras_sesiones_exige_confirmar_la_contrasena(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->delete(route('seguridad.cerrar-otras'))
            ->assertRedirect(route('password.confirm', absolute: false));
    }

    // ── Perfil ───────────────────────────────────────────────────────────────

    public function test_el_perfil_ya_no_cambia_el_correo(): void
    {
        // Desde la Fase 4.C el correo no se cambia por /profile: tiene su flujo
        // seguro en «Mi seguridad», con verificación de la dirección nueva (ver
        // CambioDeCorreoTest). Un `email` colado en el PATCH se ignora.
        $usuario = User::factory()->create(['email' => 'antes@example.com']);

        $this->actingAs($usuario)
            ->from('/profile')
            ->patch('/profile', ['name' => $usuario->name, 'email' => 'otro@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('antes@example.com', $usuario->fresh()->email);
    }

    public function test_cambiar_solo_el_nombre_no_pide_contrasena(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->from('/profile')
            ->patch('/profile', ['name' => 'Nombre Corregido', 'email' => $usuario->email])
            ->assertSessionHasNoErrors();

        $this->assertSame('Nombre Corregido', $usuario->fresh()->name);
    }

    // ── Cabeceras ────────────────────────────────────────────────────────────

    public function test_las_cabeceras_de_seguridad_van_en_todas_las_respuestas(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_la_csp_arranca_en_modo_informe_y_contempla_los_origenes_del_sitio(): void
    {
        $respuesta = $this->get('/');

        $politica = $respuesta->headers->get('Content-Security-Policy-Report-Only');

        $this->assertNotNull($politica, 'La CSP no se está emitiendo.');
        $this->assertNull(
            $respuesta->headers->get('Content-Security-Policy'),
            'La CSP no debe bloquear hasta que se revise en staging (NODICO_CSP_ESTRICTA).'
        );

        foreach (['youtube.com', 'luma.com', 'instagram.com', 'fonts.gstatic.com'] as $origen) {
            $this->assertStringContainsString($origen, $politica);
        }

        $this->assertStringContainsString("form-action 'self'", $politica);
        $this->assertStringContainsString("object-src 'none'", $politica);
    }

    /** HSTS sobre HTTP dejaría el navegador forzando https://localhost un año. */
    public function test_no_se_manda_hsts_fuera_de_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    }
}
