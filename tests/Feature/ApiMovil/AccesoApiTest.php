<?php

namespace Tests\Feature\ApiMovil;

use App\Models\EnlaceMagico;
use App\Models\User;
use App\Notifications\EnlaceDeAcceso;
use App\Support\DosFactores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Acceso de la app (docs/API-MOVIL.md §5): los tres caminos terminan en el
 * mismo emisor, y ninguno se salta el segundo factor ni la regla de las dos
 * caras.
 */
class AccesoApiTest extends TestCase
{
    use ApiMovil, RefreshDatabase;

    public function test_un_miembro_entra_con_contrasena_y_recibe_un_token_de_miembro(): void
    {
        $miembro = User::factory()->miembro()->create(['email' => 'ana@correo.mx']);

        $respuesta = $this->api('POST', 'auth/token', ['email' => 'ana@correo.mx', 'password' => 'password', ...$this->dispositivo()])
            ->assertOk()
            ->assertJsonPath('data.usuario.cara', 'miembro')
            ->assertJsonPath('data.caduca_por_inactividad_en_dias', 60);

        $token = $respuesta->json('data.token');

        $this->api('GET', 'yo', token: $token)->assertOk()->assertJsonPath('data.email', 'ana@correo.mx');
        $this->assertTrue($miembro->tokens()->first()->can('miembro'));
        $this->assertSame('telefono-de-prueba-1', $miembro->tokens()->first()->dispositivo_id);
    }

    public function test_una_contrasena_equivocada_no_da_token(): void
    {
        User::factory()->miembro()->create(['email' => 'ana@correo.mx']);

        $this->api('POST', 'auth/token', ['email' => 'ana@correo.mx', 'password' => 'mala', ...$this->dispositivo()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_staff_y_caja_no_obtienen_token(): void
    {
        foreach ([User::factory()->staff(), User::factory()->caja()] as $factory) {
            $usuario = $factory->create();

            $this->api('POST', 'auth/token', ['email' => $usuario->email, 'password' => 'password', ...$this->dispositivo()])
                ->assertStatus(403)
                ->assertJsonPath('codigo', 'cuenta_operativa');
        }

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_un_admin_recibe_un_token_de_reportes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->api('POST', 'auth/token', ['email' => $admin->email, 'password' => 'password', ...$this->dispositivo()])
            ->assertOk()
            ->assertJsonPath('data.usuario.cara', 'reportes')
            ->assertJsonPath('data.caduca_por_inactividad_en_dias', 15);

        $this->assertTrue($admin->tokens()->first()->can('reportes'));
        $this->assertFalse($admin->tokens()->first()->can('miembro'));
    }

    public function test_volver_a_entrar_desde_el_mismo_telefono_revoca_el_token_anterior(): void
    {
        $miembro = User::factory()->miembro()->create();
        $datos   = ['email' => $miembro->email, 'password' => 'password', ...$this->dispositivo('mismo-telefono')];

        $viejo = $this->api('POST', 'auth/token', $datos)->json('data.token');
        $this->api('POST', 'auth/token', $datos)->assertOk();

        $this->assertSame(1, $miembro->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->api('GET', 'yo', token: $viejo)->assertStatus(401)->assertJsonPath('codigo', 'no_autenticado');
    }

    public function test_con_segundo_factor_la_contrasena_no_basta(): void
    {
        $secreto = app(DosFactores::class)->generarSecreto();
        $miembro = User::factory()->miembro()->create();
        $miembro->forceFill(['dos_factores_secreto' => $secreto, 'dos_factores_confirmado_en' => now()])->save();

        $paso1 = $this->api('POST', 'auth/token', ['email' => $miembro->email, 'password' => 'password', ...$this->dispositivo()])
            ->assertOk()
            ->assertJsonPath('data.requiere_dos_factores', true)
            ->assertJsonMissingPath('data.token');

        $desafio = $paso1->json('data.desafio');
        $this->assertSame(0, PersonalAccessToken::count(), 'Sin el código no puede existir ningún token.');

        $this->api('POST', 'auth/dos-factores', ['desafio' => $desafio, 'codigo' => '000000'])->assertStatus(422);

        $codigo = app(Google2FA::class)->getCurrentOtp($secreto);

        $this->api('POST', 'auth/dos-factores', ['desafio' => $desafio, 'codigo' => $codigo])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'usuario']]);

        // El desafío es de un solo uso.
        $this->api('POST', 'auth/dos-factores', ['desafio' => $desafio, 'codigo' => $codigo])
            ->assertStatus(401)
            ->assertJsonPath('codigo', 'desafio_caducado');
    }

    public function test_el_desafio_se_quema_tras_cinco_codigos_incorrectos(): void
    {
        $secreto = app(DosFactores::class)->generarSecreto();
        $miembro = User::factory()->miembro()->create();
        $miembro->forceFill(['dos_factores_secreto' => $secreto, 'dos_factores_confirmado_en' => now()])->save();

        $desafio = $this->api('POST', 'auth/token', ['email' => $miembro->email, 'password' => 'password', ...$this->dispositivo()])
            ->json('data.desafio');

        for ($i = 1; $i <= 4; $i++) {
            $this->api('POST', 'auth/dos-factores', ['desafio' => $desafio, 'codigo' => '000000'])->assertStatus(422);
        }

        $this->api('POST', 'auth/dos-factores', ['desafio' => $desafio, 'codigo' => '000000'])
            ->assertStatus(401)
            ->assertJsonPath('codigo', 'desafio_caducado');

        // Ni con el código correcto: ese desafío ya no existe.
        $this->api('POST', 'auth/dos-factores', ['desafio' => $desafio, 'codigo' => app(Google2FA::class)->getCurrentOtp($secreto)])
            ->assertStatus(401);

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_pedir_desafios_nuevos_no_reinicia_el_limite_de_la_cuenta(): void
    {
        $secreto = app(DosFactores::class)->generarSecreto();
        $miembro = User::factory()->miembro()->create();
        $miembro->forceFill(['dos_factores_secreto' => $secreto, 'dos_factores_confirmado_en' => now()])->save();
        $datos = ['email' => $miembro->email, 'password' => 'password', ...$this->dispositivo()];

        // Dos desafíos con 5 fallos cada uno = 10 fallos de la cuenta.
        foreach ([1, 2] as $_) {
            $desafio = $this->api('POST', 'auth/token', $datos)->json('data.desafio');
            for ($i = 1; $i <= 5; $i++) {
                $this->api('POST', 'auth/dos-factores', ['desafio' => $desafio, 'codigo' => '000000']);
            }
        }

        // Un tercer desafío, aunque traiga el código correcto, choca con el límite.
        $desafio = $this->api('POST', 'auth/token', $datos)->json('data.desafio');

        $this->api('POST', 'auth/dos-factores', ['desafio' => $desafio, 'codigo' => app(Google2FA::class)->getCurrentOtp($secreto)])
            ->assertStatus(429)
            ->assertJsonPath('codigo', 'demasiadas_peticiones');

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_cerrar_sesion_invalida_ese_token(): void
    {
        $miembro = User::factory()->miembro()->create();
        $token   = $this->tokenDe($miembro);

        $this->api('POST', 'auth/salir', token: $token)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->api('GET', 'yo', token: $token)->assertStatus(401);
        $this->assertSame(0, $miembro->tokens()->count());
    }

    public function test_un_token_sin_usar_60_dias_caduca(): void
    {
        $miembro = User::factory()->miembro()->create();
        $token   = $this->tokenDe($miembro);

        $miembro->tokens()->update(['last_used_at' => now()->subDays(61), 'created_at' => now()->subDays(90)]);

        $this->api('GET', 'yo', token: $token)->assertStatus(401)->assertJsonPath('codigo', 'no_autenticado');
    }

    public function test_un_token_usado_hace_59_dias_sigue_vivo(): void
    {
        $miembro = User::factory()->miembro()->create();
        $token   = $this->tokenDe($miembro);

        $miembro->tokens()->update(['last_used_at' => now()->subDays(59), 'created_at' => now()->subDays(90)]);

        $this->api('GET', 'yo', token: $token)->assertOk();
    }

    public function test_el_token_de_reportes_caduca_a_los_15_dias(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->tokenDe($admin, 'reportes');

        $admin->tokens()->update(['last_used_at' => now()->subDays(16)]);

        $this->api('GET', 'reportes/resumen', token: $token)->assertStatus(401);
    }

    public function test_enlace_magico_solo_se_canjea_con_el_secreto_de_ese_telefono_y_una_vez(): void
    {
        config(['nodico.acceso.enlace_magico' => true]);
        Notification::fake();

        $miembro = User::factory()->miembro()->create(['email' => 'ana@correo.mx']);
        $secreto = Str::random(48);

        $this->api('POST', 'auth/enlace-magico', ['email' => 'ana@correo.mx', 'verificador' => hash('sha256', $secreto)])
            ->assertOk()
            ->assertJsonPath('data.enviado', true);

        $token = null;
        Notification::assertSentTo($miembro, EnlaceDeAcceso::class, function (EnlaceDeAcceso $n) use (&$token, $miembro) {
            preg_match('#/app/enlace/([A-Za-z0-9]{48})#', $n->toMail($miembro)->viewData['url'], $m);
            $token = $m[1] ?? null;

            return $token !== null;
        });

        // Otro teléfono (otro secreto) no entra.
        $this->api('POST', 'auth/enlace-magico/canjear', ['token' => $token, 'secreto' => Str::random(48), ...$this->dispositivo()])
            ->assertStatus(422)
            ->assertJsonPath('codigo', 'enlace_otro_dispositivo');

        $this->api('POST', 'auth/enlace-magico/canjear', ['token' => $token, 'secreto' => $secreto, ...$this->dispositivo()])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token']]);

        // Un solo uso.
        $this->api('POST', 'auth/enlace-magico/canjear', ['token' => $token, 'secreto' => $secreto, ...$this->dispositivo()])
            ->assertStatus(422)
            ->assertJsonPath('codigo', 'enlace_invalido');
    }

    public function test_pedir_enlace_responde_igual_exista_o_no_la_cuenta(): void
    {
        config(['nodico.acceso.enlace_magico' => true]);
        Notification::fake();
        User::factory()->miembro()->create(['email' => 'ana@correo.mx']);

        $existe  = $this->api('POST', 'auth/enlace-magico', ['email' => 'ana@correo.mx', 'verificador' => hash('sha256', 'x')])->json();
        $noExiste = $this->api('POST', 'auth/enlace-magico', ['email' => 'nadie@correo.mx', 'verificador' => hash('sha256', 'x')])->json();

        $this->assertSame($existe, $noExiste);
        $this->assertSame(1, EnlaceMagico::count());
    }

    public function test_un_id_token_de_google_emitido_para_otra_app_se_rechaza(): void
    {
        config([
            'nodico.acceso.google' => true,
            'nodico.app_movil.google_client_ids' => ['nuestro-cliente.apps.googleusercontent.com'],
        ]);

        Http::fake(['oauth2.googleapis.com/*' => Http::response([
            'aud' => 'otra-app.apps.googleusercontent.com', 'iss' => 'accounts.google.com',
            'exp' => time() + 600, 'sub' => '123', 'email' => 'ana@correo.mx', 'email_verified' => 'true',
        ])]);

        User::factory()->miembro()->create(['email' => 'ana@correo.mx']);

        $this->api('POST', 'auth/google', ['id_token' => 'x.y.z', ...$this->dispositivo()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('id_token');

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_un_id_token_de_google_valido_vincula_y_entra(): void
    {
        config([
            'nodico.acceso.google' => true,
            'nodico.app_movil.google_client_ids' => ['nuestro-cliente.apps.googleusercontent.com'],
        ]);

        Http::fake(['oauth2.googleapis.com/*' => Http::response([
            'aud' => 'nuestro-cliente.apps.googleusercontent.com', 'iss' => 'https://accounts.google.com',
            'exp' => time() + 600, 'sub' => '123', 'email' => 'ana@correo.mx', 'email_verified' => 'true',
            'name' => 'Ana',
        ])]);

        $ana = User::factory()->miembro()->create(['email' => 'ana@correo.mx']);

        $this->api('POST', 'auth/google', ['id_token' => 'x.y.z', ...$this->dispositivo()])
            ->assertOk()
            ->assertJsonPath('data.usuario.id', $ana->id);

        $this->assertDatabaseHas('identidades_sociales', ['user_id' => $ana->id, 'proveedor' => 'google', 'proveedor_id' => '123']);
    }

    public function test_una_version_vieja_de_la_app_recibe_409(): void
    {
        config(['nodico.app_movil.version_minima.ios' => '2.0.0']);

        $this->api('GET', 'estado')->assertStatus(409)->assertJsonPath('codigo', 'version_obsoleta');
    }
}
