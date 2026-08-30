<?php

namespace Tests\Feature\Auth;

use App\Models\Consentimiento;
use App\Models\User;
use App\Support\DocumentosLegales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Fase E — aviso de privacidad y términos.
 *
 * Requisito legal en México (LFPDPPP), no un detalle de forma.
 */
class ConsentimientoTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE = 'MiClaveNodico2026';

    /**
     * @return array<string, mixed>
     */
    private function datosDeAlta(array $extra = []): array
    {
        return array_merge([
            'name'                  => 'Persona Nueva',
            'email'                 => 'nueva@example.com',
            'password'              => self::CLAVE,
            'password_confirmation' => self::CLAVE,
            'acepta_legales'        => true,
        ], $extra);
    }

    // ── Registro ─────────────────────────────────────────────────────────────

    /**
     * La casilla se valida **en el servidor**. En el formulario ya bloquea el
     * envío, pero eso es cortesía: sin la regla bastaría un `curl` para crear
     * cuentas sin consentimiento.
     */
    public function test_el_registro_sin_aceptar_la_casilla_falla(): void
    {
        $this->post('/register', $this->datosDeAlta(['acepta_legales' => false]))
            ->assertSessionHasErrors('acepta_legales');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('consentimientos', 0);
    }

    public function test_el_registro_sin_el_campo_siquiera_tambien_falla(): void
    {
        $datos = $this->datosDeAlta();
        unset($datos['acepta_legales']);

        $this->post('/register', $datos)->assertSessionHasErrors('acepta_legales');

        $this->assertDatabaseCount('users', 0);
    }

    /** Lo que de verdad importa el día que alguien lo reclame. */
    public function test_la_constancia_se_guarda_con_version_fecha_e_ip(): void
    {
        Notification::fake();

        $this->post('/register', $this->datosDeAlta());

        $usuario   = User::where('email', 'nueva@example.com')->firstOrFail();
        $versiones = app(DocumentosLegales::class)->versiones();

        // Una fila por documento, no un booleano.
        $this->assertCount(count($versiones), $usuario->consentimientos()->get());

        foreach ($versiones as $documento => $version) {
            $this->assertDatabaseHas('consentimientos', [
                'user_id'   => $usuario->id,
                'documento' => $documento,
                'version'   => $version,
            ]);
        }

        $constancia = $usuario->consentimientos()->first();

        $this->assertNotNull($constancia->aceptado_en);
        $this->assertNotNull($constancia->ip);
    }

    // ── Versionado ───────────────────────────────────────────────────────────

    /**
     * Se guarda la **versión**, no un sí/no. Por eso, cuando el IYEM publique
     * una versión nueva, quien tenga aceptada la anterior vuelve a ser
     * preguntado.
     */
    public function test_una_version_nueva_vuelve_a_pedir_el_consentimiento(): void
    {
        $usuario = User::factory()->miembro()->sinConsentimiento()->create();

        // Acepta lo vigente hoy.
        Consentimiento::registrar($usuario, request());
        $this->assertSame([], $usuario->fresh()->consentimientosPendientes());

        // El IYEM publica una revisión.
        $this->simularVersion('2.0');

        $this->assertSame(
            ['privacidad', 'terminos'],
            $usuario->fresh()->consentimientosPendientes(),
        );
    }

    public function test_el_portal_no_se_usa_sin_aceptar_la_version_vigente(): void
    {
        $miembro = User::factory()->miembro()->sinConsentimiento()->create();

        $this->actingAs($miembro)
            ->get('/portal')
            ->assertRedirect(route('consentimiento', absolute: false));
    }

    public function test_tras_aceptar_se_entra_al_portal(): void
    {
        $miembro = User::factory()->miembro()->sinConsentimiento()->create();

        $this->actingAs($miembro)
            ->post(route('consentimiento.guardar'), ['acepta' => true])
            ->assertRedirect(route('portal.dashboard', absolute: false));

        $this->actingAs($miembro->fresh())->get('/portal')->assertOk();
    }

    public function test_no_aceptar_en_la_pantalla_de_consentimiento_no_deja_pasar(): void
    {
        $miembro = User::factory()->miembro()->sinConsentimiento()->create();

        $this->actingAs($miembro)
            ->post(route('consentimiento.guardar'), ['acepta' => false])
            ->assertSessionHasErrors('acepta');

        $this->assertDatabaseCount('consentimientos', 0);
    }

    /**
     * Sin bucle, como la pantalla de cuenta suspendida: la ruta destino no
     * lleva el middleware que redirige hacia ella.
     */
    public function test_la_pantalla_de_consentimiento_no_se_redirige_a_si_misma(): void
    {
        $miembro = User::factory()->miembro()->sinConsentimiento()->create();

        $respuesta = $this->actingAs($miembro)->get(route('consentimiento'));

        $respuesta->assertOk();
        $this->assertFalse($respuesta->isRedirect());
    }

    /** Quien ya está al día no debe quedarse atrapado en la pantalla. */
    public function test_sin_nada_pendiente_la_pantalla_manda_al_portal(): void
    {
        $miembro = User::factory()->miembro()->create();

        $this->actingAs($miembro->fresh())
            ->get(route('consentimiento'))
            ->assertRedirect(route('portal.dashboard', absolute: false));
    }

    // ── Derechos ARCO ────────────────────────────────────────────────────────

    public function test_cada_quien_puede_descargar_sus_datos(): void
    {
        $miembro = User::factory()->miembro()->create(['name' => 'Quien Sea']);

        $respuesta = $this->actingAs($miembro->fresh())->get(route('datos.descargar'));

        $respuesta->assertOk();
        $respuesta->assertHeader('content-type', 'application/json; charset=UTF-8');

        $contenido = $respuesta->streamedContent();
        $datos     = json_decode($contenido, true);

        $this->assertSame('Quien Sea', $datos['cuenta']['nombre']);
        $this->assertNotEmpty($datos['consentimientos']);

        // `notas_admin` son apuntes internos del equipo, no datos de la persona.
        $this->assertArrayNotHasKey('notas_admin', $datos['cuenta']);
    }

    public function test_el_perfil_muestra_lo_aceptado(): void
    {
        $miembro = User::factory()->miembro()->create();

        $this->actingAs($miembro->fresh())
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Profile/Edit')
                ->has('consentimientos', 2)
                ->where('legalProvisional', true));
    }

    /**
     * BE-04 — mientras el área jurídica del IYEM no valide los textos, la
     * interfaz lo dice. El mecanismo funciona; la validez legal no depende de
     * él, y callarlo daría a entender lo contrario.
     */
    public function test_se_avisa_de_que_los_textos_son_provisionales(): void
    {
        $this->assertTrue(app(DocumentosLegales::class)->algunoEsProvisional());
    }

    /** Cambia la versión de los documentos en disco durante una prueba. */
    private function simularVersion(string $version): void
    {
        foreach (DocumentosLegales::DOCUMENTOS as $doc) {
            $ruta  = resource_path("legal/{$doc['archivo']}.md");
            $crudo = file_get_contents($ruta);

            file_put_contents(
                $ruta,
                preg_replace('/^version: .*$/m', "version: {$version}", $crudo, 1),
            );

            $this->beforeApplicationDestroyed(function () use ($ruta, $crudo) {
                file_put_contents($ruta, $crudo);
            });
        }

        // El servicio memoriza lo leído dentro de la petición.
        app()->forgetInstance(DocumentosLegales::class);
    }
}
