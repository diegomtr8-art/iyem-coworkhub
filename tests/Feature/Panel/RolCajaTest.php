<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El rol Caja ve SOLO su mostrador de cobros: nada del resto del panel.
 */
class RolCajaTest extends TestCase
{
    use RefreshDatabase;

    public function test_caja_entra_a_su_mostrador(): void
    {
        $this->actingAs(User::factory()->caja()->create())
            ->get(route('caja.ordenes'))->assertOk();
    }

    public function test_caja_no_ve_facturacion_ni_accesos_ni_miembros(): void
    {
        $caja = User::factory()->caja()->create();

        $this->actingAs($caja)->get(route('facturas.index'))->assertForbidden();
        $this->actingAs($caja)->get(route('accesos.index'))->assertForbidden();
        $this->actingAs($caja)->get(route('miembros.index'))->assertForbidden();
        $this->actingAs($caja)->get(route('planes.index'))->assertForbidden();
    }

    public function test_caja_solo_tiene_el_permiso_operar_caja(): void
    {
        $caja = User::factory()->caja()->create();

        $this->assertTrue($caja->can('operar-caja'));
        $this->assertFalse($caja->can('operar-checkins'));
        $this->assertFalse($caja->can('gestionar-facturacion'));
        $this->assertFalse($caja->can('ver-miembros'));
    }

    public function test_administracion_sigue_viendo_caja_y_todo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->can('operar-caja'));
        $this->assertTrue($admin->can('gestionar-facturacion'));
        $this->actingAs($admin)->get(route('caja.ordenes'))->assertOk();
    }

    public function test_recepcion_no_entra_a_caja(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('caja.ordenes'))->assertForbidden();
    }
}
