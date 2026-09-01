<?php

namespace Tests\Feature\Pagos;

use App\Enums\EstadoPagoOrden;
use App\Enums\MetodoReferencia;
use App\Models\OrdenPago;
use App\Models\Plane;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4 — caja confirma el pago, y eso activa la membresía.
 */
class CajaConfirmacionTest extends TestCase
{
    use RefreshDatabase;

    private function ordenGenerada(User $miembro, Plane $plan, float $monto): OrdenPago
    {
        $ref = OrdenPago::nuevaReferencia();
        return OrdenPago::create([
            'referencia' => $ref['referencia'], 'referencia_normalizada' => $ref['referencia_normalizada'],
            'user_id' => $miembro->id, 'plan_id' => $plan->id, 'monto' => $monto,
            'metodo' => MetodoReferencia::Transferencia, 'vence_el' => now()->addDays(7),
        ]);
    }

    private function confirmacion(array $extra = []): array
    {
        return array_merge([
            'fecha_pago' => now()->toDateString(), 'monto_recibido' => 599, 'evidencia' => 'FOLIO-123',
        ], $extra);
    }

    public function test_confirmar_activa_la_membresia_y_crea_la_suscripcion(): void
    {
        $admin   = User::factory()->admin()->create();
        $miembro = User::factory()->miembro()->create();
        $plan    = Plane::factory()->nodoPro()->create(['precio' => 599]);
        $orden   = $this->ordenGenerada($miembro, $plan, 599);

        $this->actingAs($admin)
            ->post(route('caja.confirmar', $orden), $this->confirmacion())
            ->assertRedirect();

        $orden->refresh();
        $this->assertSame(EstadoPagoOrden::Confirmada, $orden->estado_pago);
        $this->assertNotNull($orden->suscripcion_id);
        $this->assertNotNull($orden->confirmada_por_user_id);
        $this->assertTrue($miembro->suscripciones()->where('estatus', 'Activa')->exists());
    }

    public function test_confirmar_dos_veces_no_crea_dos_suscripciones(): void
    {
        $admin   = User::factory()->admin()->create();
        $miembro = User::factory()->miembro()->create();
        $plan    = Plane::factory()->nodoPro()->create(['precio' => 599]);
        $orden   = $this->ordenGenerada($miembro, $plan, 599);

        $this->actingAs($admin)->post(route('caja.confirmar', $orden), $this->confirmacion());
        $suscripciones = Suscripcion::count();

        $this->actingAs($admin)->post(route('caja.confirmar', $orden), $this->confirmacion())
            ->assertSessionHasErrors('orden');

        $this->assertSame($suscripciones, Suscripcion::count());
    }

    public function test_un_miembro_no_puede_confirmar_pagos(): void
    {
        $miembro = User::factory()->miembro()->create();
        $plan    = Plane::factory()->nodoPro()->create(['precio' => 599]);
        $orden   = $this->ordenGenerada($miembro, $plan, 599);

        $this->actingAs($miembro)
            ->post(route('caja.confirmar', $orden), $this->confirmacion())
            ->assertForbidden();

        $this->assertSame(EstadoPagoOrden::Generada, $orden->fresh()->estado_pago);
    }

    public function test_un_monto_distinto_no_se_confirma_sin_decidir(): void
    {
        $admin   = User::factory()->admin()->create();
        $miembro = User::factory()->miembro()->create();
        $plan    = Plane::factory()->nodoPro()->create(['precio' => 599]);
        $orden   = $this->ordenGenerada($miembro, $plan, 599);

        $this->actingAs($admin)
            ->post(route('caja.confirmar', $orden), $this->confirmacion(['monto_recibido' => 500]))
            ->assertSessionHasErrors('monto_recibido');

        $this->assertSame(EstadoPagoOrden::Generada, $orden->fresh()->estado_pago);
    }

    public function test_cancelar_deja_la_orden_cancelada_con_motivo(): void
    {
        $admin   = User::factory()->admin()->create();
        $miembro = User::factory()->miembro()->create();
        $plan    = Plane::factory()->nodoPro()->create(['precio' => 599]);
        $orden   = $this->ordenGenerada($miembro, $plan, 599);

        $this->actingAs($admin)->post(route('caja.cancelar', $orden), ['motivo' => 'Duplicada'])->assertRedirect();

        $orden->refresh();
        $this->assertSame(EstadoPagoOrden::Cancelada, $orden->estado_pago);
        $this->assertSame('Duplicada', $orden->motivo_cancelacion);
    }
}
