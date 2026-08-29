<?php

namespace Tests\Feature\Auth;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase A — identidad, roles y los bugs que arrastraba la autenticacion.
 */
class RolesYPortalesTest extends TestCase
{
    use RefreshDatabase;

    // ── A.1 · el bucle de redireccion ────────────────────────────────────────

    /**
     * La regresion que importa. `EsAdmin` mandaba al portal a quien no fuera
     * admin y `EsMiembro` mandaba al panel a quien no fuera miembro; un rol que
     * no fuera ninguno de los dos rebotaba entre /dashboard y /portal hasta que
     * el navegador cortaba la cadena.
     *
     * El valor invalido se pone en memoria a proposito: la columna es un enum y
     * la base no lo aceptaria. Lo que se prueba es el camino del middleware
     * ante un rol que no reconoce, que es exactamente lo que pasa en MySQL no
     * estricto (guarda cadena vacia) o el dia que se anada un rol en la base
     * antes que en el codigo.
     */
    public function test_un_rol_desconocido_recibe_403_y_nunca_una_redireccion(): void
    {
        $usuario = User::factory()->create();
        $usuario->forceFill(['tipo' => 'coordinador']);

        $this->assertNull($usuario->rol, 'Un tipo desconocido debe resolverse a null, no lanzar.');

        foreach (['/dashboard', '/portal'] as $ruta) {
            $respuesta = $this->actingAs($usuario)->get($ruta);

            $respuesta->assertStatus(403);
            $this->assertFalse(
                $respuesta->isRedirect(),
                "{$ruta} redirigio en vez de cortar: el bucle de A.1 esta de vuelta."
            );
        }
    }

    public function test_un_miembro_en_el_panel_operativo_recibe_403_no_una_redireccion(): void
    {
        $miembro = User::factory()->miembro()->create();

        $respuesta = $this->actingAs($miembro)->get('/dashboard');

        $respuesta->assertStatus(403);
        $this->assertFalse($respuesta->isRedirect());
    }

    public function test_un_admin_en_el_portal_de_miembros_recibe_403_no_una_redireccion(): void
    {
        $admin = User::factory()->admin()->create();

        $respuesta = $this->actingAs($admin)->get('/portal');

        $respuesta->assertStatus(403);
        $this->assertFalse($respuesta->isRedirect());
    }

    public function test_el_403_se_renderiza_como_pantalla_y_no_como_pagina_en_blanco(): void
    {
        $miembro = User::factory()->miembro()->create();

        $this->actingAs($miembro)
            ->get('/dashboard')
            ->assertStatus(403)
            ->assertInertia(fn ($pagina) => $pagina->component('Errors/403')->has('mensaje'));
    }

    // ── A.3 · el rol staff ───────────────────────────────────────────────────

    public function test_recepcion_entra_al_panel_operativo(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/dashboard')->assertOk();
    }

    /** Operacion diaria: entradas y salidas, reservas y directorio de miembros. */
    public static function rutasDeOperacion(): array
    {
        return [
            'check-ins' => ['/checkins'],
            'reservas'  => ['/reservas'],
            'miembros'  => ['/miembros'],
        ];
    }

    /** @dataProvider rutasDeOperacion */
    public function test_recepcion_puede_operar_el_dia_a_dia(string $ruta): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get($ruta)->assertOk();
    }

    /** Configuracion del negocio y dinero: fuera del alcance de recepcion. */
    public static function rutasDeAdministracion(): array
    {
        return [
            'planes'   => ['/planes'],
            'espacios' => ['/espacios'],
            'facturas' => ['/facturas'],
            'reportes' => ['/reportes'],
            'eventos'  => ['/admin/eventos'],
        ];
    }

    /** @dataProvider rutasDeAdministracion */
    public function test_recepcion_no_toca_configuracion_ni_facturacion(string $ruta): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get($ruta)->assertStatus(403);
    }

    /** @dataProvider rutasDeAdministracion */
    public function test_administracion_si_entra_a_todo(string $ruta): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get($ruta)->assertOk();
    }

    public function test_recepcion_no_puede_activar_face_id_de_un_miembro(): void
    {
        $staff   = User::factory()->staff()->create();
        $miembro = User::factory()->miembro()->create();

        $this->actingAs($staff)
            ->patch("/miembros/{$miembro->id}/faceid")
            ->assertStatus(403);
    }

    // ── A.4 · `tipo` fuera de $fillable ──────────────────────────────────────

    public function test_el_formulario_de_registro_no_puede_elegir_el_rol(): void
    {
        $this->post('/register', [
            'name'                  => 'Intrusa Curiosa',
            'email'                 => 'intrusa@example.com',
            'password'              => 'MiClaveNodico2026',
            'password_confirmation' => 'MiClaveNodico2026',
            'tipo'                  => RolUsuario::Admin->value,
            'estado_cuenta'         => EstadoCuenta::Activa->value,
        ]);

        $usuario = User::where('email', 'intrusa@example.com')->firstOrFail();

        $this->assertSame(RolUsuario::Miembro, $usuario->rol);
        $this->assertSame(EstadoCuenta::Pendiente, $usuario->estado);
    }

    public function test_una_asignacion_masiva_no_puede_cambiar_el_rol(): void
    {
        $miembro = User::factory()->miembro()->create();

        $miembro->update(['name' => 'Nombre Nuevo', 'tipo' => RolUsuario::Admin->value]);

        $this->assertSame('Nombre Nuevo', $miembro->fresh()->name);
        $this->assertSame(RolUsuario::Miembro, $miembro->fresh()->rol);
    }

    public function test_el_rol_solo_cambia_por_metodo_explicito(): void
    {
        $usuario = User::factory()->miembro()->create();

        $usuario->asignarRol(RolUsuario::Staff);

        $this->assertSame(RolUsuario::Staff, $usuario->fresh()->rol);
    }

    // ── A.5 · estado de cuenta, separado del rol ─────────────────────────────

    public function test_la_membresia_nace_pendiente_y_el_estado_es_independiente_del_rol(): void
    {
        $this->post('/register', [
            'name'                  => 'Nueva Emprendedora',
            'email'                 => 'nueva@example.com',
            'password'              => 'MiClaveNodico2026',
            'password_confirmation' => 'MiClaveNodico2026',
        ]);

        $usuario = User::where('email', 'nueva@example.com')->firstOrFail();

        $this->assertSame(EstadoCuenta::Pendiente, $usuario->estado);
        $this->assertFalse($usuario->cuentaActiva());

        // El rol no se mueve al activar la cuenta, ni al reves.
        $usuario->cambiarEstado(EstadoCuenta::Activa);

        $this->assertTrue($usuario->fresh()->cuentaActiva());
        $this->assertSame(RolUsuario::Miembro, $usuario->fresh()->rol);
    }

    public function test_un_estado_ilegible_se_trata_como_pendiente_nunca_como_activo(): void
    {
        $usuario = User::factory()->create();
        $usuario->forceFill(['estado_cuenta' => 'lo-que-sea']);

        $this->assertSame(EstadoCuenta::Pendiente, $usuario->estado);
        $this->assertFalse($usuario->cuentaActiva());
    }
}
