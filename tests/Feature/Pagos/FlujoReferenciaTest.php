<?php

namespace Tests\Feature\Pagos;

use App\Enums\EstadoFacturaOrden;
use App\Enums\EstadoPagoOrden;
use App\Models\DatosFiscales;
use App\Models\OrdenPago;
use App\Models\Plane;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fases 2 y 3 — elegir método, generar la referencia y verla.
 */
class FlujoReferenciaTest extends TestCase
{
    use RefreshDatabase;

    private function fiscales(User $user): void
    {
        DatosFiscales::create([
            'user_id'          => $user->id,
            'rfc'              => 'XAXX010101000',
            'razon_social'     => 'Emprendedora Demo',
            'regimen_fiscal'   => '601',
            'uso_cfdi'         => 'G03',
            'codigo_postal'    => '97000',
            'email_facturacion' => 'facturas@demo.mx',
        ]);
    }

    public function test_genera_una_orden_por_transferencia_sin_factura(): void
    {
        $user = User::factory()->miembro()->create();
        $plan = Plane::factory()->nodoPro()->create(['precio' => 599]);

        $this->actingAs($user)
            ->post(route('portal.referencia.generar', $plan), ['metodo' => 'transferencia', 'pide_factura' => false])
            ->assertRedirect();

        $orden = OrdenPago::firstOrFail();
        $this->assertSame(EstadoPagoOrden::Generada, $orden->estado_pago);
        $this->assertSame(EstadoFacturaOrden::NoSolicitada, $orden->estado_factura);
        $this->assertEquals(599, $orden->monto);
        $this->assertNull($orden->fiscal_rfc);
        $this->assertMatchesRegularExpression('/^NDC-/', $orden->referencia);
    }

    public function test_pedir_factura_sin_datos_fiscales_no_genera_orden(): void
    {
        $user = User::factory()->miembro()->create();
        $plan = Plane::factory()->nodoPro()->create();

        $this->actingAs($user)
            ->post(route('portal.referencia.generar', $plan), ['metodo' => 'transferencia', 'pide_factura' => true])
            ->assertRedirect(route('portal.datos-fiscales'));

        $this->assertSame(0, OrdenPago::count());
    }

    public function test_con_factura_guarda_la_foto_fiscal_y_marca_solicitada(): void
    {
        $user = User::factory()->miembro()->create();
        $this->fiscales($user);
        $plan = Plane::factory()->nodoPro()->create();

        $this->actingAs($user)
            ->post(route('portal.referencia.generar', $plan), ['metodo' => 'efectivo', 'pide_factura' => true])
            ->assertRedirect();

        $orden = OrdenPago::firstOrFail();
        $this->assertTrue($orden->pide_factura);
        $this->assertSame(EstadoFacturaOrden::Solicitada, $orden->estado_factura);
        $this->assertSame('XAXX010101000', $orden->fiscal_rfc);
    }

    public function test_un_miembro_no_ve_la_referencia_de_otro(): void
    {
        $duena = User::factory()->miembro()->create();
        $plan  = Plane::factory()->nodoPro()->create();
        $this->actingAs($duena)->post(route('portal.referencia.generar', $plan), ['metodo' => 'transferencia', 'pide_factura' => false]);
        $orden = OrdenPago::firstOrFail();

        $intrusa = User::factory()->miembro()->create();
        $this->actingAs($intrusa)->get(route('portal.referencia.mostrar', $orden))->assertForbidden();
    }

    public function test_ya_pague_sube_la_orden_sin_confirmar_nada(): void
    {
        $user = User::factory()->miembro()->create();
        $plan = Plane::factory()->nodoPro()->create();
        $this->actingAs($user)->post(route('portal.referencia.generar', $plan), ['metodo' => 'transferencia', 'pide_factura' => false]);
        $orden = OrdenPago::firstOrFail();

        $this->actingAs($user)->post(route('portal.referencia.ya-pague', $orden))->assertRedirect();

        $orden->refresh();
        $this->assertNotNull($orden->reportado_pagado_en);
        $this->assertSame(EstadoPagoOrden::Generada, $orden->estado_pago, '«Ya pagué» no debe confirmar nada.');
    }
}
