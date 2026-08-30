<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase G — a dónde va cada quien después de identificarse.
 */
class DestinoTrasEntrarTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `redirect()->intended()` a secas manda a la última URL que se intentó
     * abrir. Un miembro con una URL del panel guardada entraba **bien** y aun
     * así aterrizaba en un 403.
     */
    public function test_un_miembro_no_acaba_en_el_panel_por_un_destino_previsto(): void
    {
        $miembro = User::factory()->miembro()->create(['email' => 'quien@example.com']);

        // Intenta abrir el panel: se le pide identificarse y queda guardado.
        $this->get('/reportes')->assertRedirect(route('login', absolute: false));

        $this->post('/login', ['email' => 'quien@example.com', 'password' => 'password'])
            ->assertRedirect(route('portal.dashboard', absolute: false));

        $this->assertAuthenticatedAs($miembro);
    }

    /** Y al revés: el equipo no acaba en el portal de miembros. */
    public function test_el_equipo_no_acaba_en_el_portal_por_un_destino_previsto(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->get('/portal/mis-facturas')->assertRedirect(route('login', absolute: false));

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
    }

    /** Un destino que sí le corresponde se respeta: para eso existe. */
    public function test_el_destino_previsto_de_su_propio_portal_se_respeta(): void
    {
        User::factory()->miembro()->create(['email' => 'quien@example.com']);

        $this->get('/portal/mis-facturas')->assertRedirect(route('login', absolute: false));

        $this->post('/login', ['email' => 'quien@example.com', 'password' => 'password'])
            ->assertRedirect('/portal/mis-facturas');
    }

    /** Las rutas sin portal —el perfil, «Mi seguridad»— valen para cualquiera. */
    public function test_una_ruta_comun_se_respeta_para_los_dos_roles(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->get('/seguridad')->assertRedirect(route('login', absolute: false));

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect('/seguridad');
    }

    /**
     * El destino previsto sale de la sesión, pero cerrar un redirector abierto
     * cuesta una línea y evita que un fallo futuro lo convierta en uno.
     */
    public function test_un_destino_en_otro_host_se_descarta(): void
    {
        User::factory()->miembro()->create(['email' => 'quien@example.com']);

        $this->withSession(['url.intended' => 'https://sitio-ajeno.example/robar'])
            ->post('/login', ['email' => 'quien@example.com', 'password' => 'password'])
            ->assertRedirect(route('portal.dashboard', absolute: false));
    }

    public function test_un_destino_que_no_existe_se_descarta(): void
    {
        User::factory()->miembro()->create(['email' => 'quien@example.com']);

        $this->withSession(['url.intended' => '/esta-ruta-no-existe-en-ninguna-parte'])
            ->post('/login', ['email' => 'quien@example.com', 'password' => 'password'])
            ->assertRedirect(route('portal.dashboard', absolute: false));
    }

    /** G — El panel recomienda el segundo factor mientras no esté activo. */
    public function test_el_panel_avisa_del_segundo_factor_a_quien_no_lo_tiene(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('auth.user.dosFactores', false));
    }
}
