<?php

namespace Tests\Feature\Pagos;

use App\Enums\EstadoFacturaOrden;
use App\Enums\EstadoPagoOrden;
use App\Enums\MetodoReferencia;
use App\Models\OrdenPago;
use App\Models\Plane;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «Mis facturas»: el miembro ve SOLO sus facturas ya emitidas, listas para bajar.
 */
class MisFacturasTest extends TestCase
{
    use RefreshDatabase;

    private function orden(User $miembro, string $estadoFactura, array $extra = []): OrdenPago
    {
        $plan = Plane::factory()->nodoPro()->create(['precio' => 599]);
        $ref  = OrdenPago::nuevaReferencia();

        return OrdenPago::create(array_merge([
            'referencia' => $ref['referencia'], 'referencia_normalizada' => $ref['referencia_normalizada'],
            'user_id' => $miembro->id, 'plan_id' => $plan->id, 'monto' => 599,
            'metodo' => MetodoReferencia::Transferencia, 'pide_factura' => true,
            'estado_pago' => EstadoPagoOrden::Confirmada, 'estado_factura' => $estadoFactura,
            'vence_el' => now()->addDays(7),
        ], $extra));
    }

    public function test_solo_aparecen_las_facturas_emitidas_del_miembro(): void
    {
        $miembro = User::factory()->miembro()->create();

        $emitida = $this->orden($miembro, EstadoFacturaOrden::Emitida->value, [
            'factura_pdf' => 'facturas/1/x.pdf', 'folio_fiscal' => 'ABC-123', 'factura_emitida_en' => now(),
        ]);
        // Solicitada pero sin emitir: NO debe salir.
        $this->orden($miembro, EstadoFacturaOrden::Solicitada->value);

        $this->actingAs($miembro)->get(route('portal.facturas'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Portal/MisFacturas')
                ->has('facturas', 1)
                ->where('facturas.0.id', $emitida->id)
                ->where('facturas.0.folio_fiscal', 'ABC-123'));
    }

    public function test_no_veo_las_facturas_de_otro_miembro(): void
    {
        $otro = User::factory()->miembro()->create();
        $this->orden($otro, EstadoFacturaOrden::Emitida->value, [
            'factura_pdf' => 'facturas/9/y.pdf', 'folio_fiscal' => 'OTRA-999', 'factura_emitida_en' => now(),
        ]);

        $this->actingAs(User::factory()->miembro()->create())
            ->get(route('portal.facturas'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->has('facturas', 0));
    }
}
