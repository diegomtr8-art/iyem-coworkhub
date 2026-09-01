<?php

namespace Tests\Feature\Pagos;

use App\Enums\EstadoFacturaOrden;
use App\Enums\EstadoPagoOrden;
use App\Enums\MetodoReferencia;
use App\Models\OrdenPago;
use App\Models\Plane;
use App\Models\User;
use App\Notifications\FacturaEmitida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fase 5 — contabilidad sube la factura y la envía al miembro.
 */
class FacturasReferenciaTest extends TestCase
{
    use RefreshDatabase;

    private function ordenConFactura(string $estadoFactura = 'solicitada', array $extra = []): OrdenPago
    {
        $miembro = User::factory()->miembro()->create();
        $plan    = Plane::factory()->nodoPro()->create(['precio' => 599]);
        $ref     = OrdenPago::nuevaReferencia();

        return OrdenPago::create(array_merge([
            'referencia' => $ref['referencia'], 'referencia_normalizada' => $ref['referencia_normalizada'],
            'user_id' => $miembro->id, 'plan_id' => $plan->id, 'monto' => 599,
            'metodo' => MetodoReferencia::Transferencia, 'pide_factura' => true,
            'estado_pago' => EstadoPagoOrden::Confirmada, 'estado_factura' => $estadoFactura,
            'fiscal_rfc' => 'XAXX010101000', 'vence_el' => now()->addDays(7),
        ], $extra));
    }

    public function test_subir_la_factura_la_marca_emitida(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $orden = $this->ordenConFactura();

        $this->actingAs($admin)->post(route('caja.factura.subir', $orden), [
            'folio_fiscal' => 'A1B2-UUID-FOLIO',
            'pdf' => UploadedFile::fake()->create('factura.pdf', 120, 'application/pdf'),
            'xml' => UploadedFile::fake()->create('factura.xml', 40, 'text/xml'),
        ])->assertRedirect();

        $orden->refresh();
        $this->assertSame(EstadoFacturaOrden::Emitida, $orden->estado_factura);
        $this->assertNotNull($orden->factura_pdf);
        $this->assertSame('A1B2-UUID-FOLIO', $orden->folio_fiscal);
        Storage::disk('local')->assertExists($orden->factura_pdf);
    }

    public function test_enviar_la_factura_notifica_y_la_marca_enviada(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $orden = $this->ordenConFactura('emitida', [
            'factura_pdf' => 'facturas/1/x.pdf', 'factura_xml' => 'facturas/1/x.xml',
            'folio_fiscal' => 'FOLIO-1', 'factura_emitida_en' => now(),
        ]);

        $this->actingAs($admin)->post(route('caja.factura.enviar', $orden))->assertRedirect();

        $orden->refresh();
        $this->assertSame(EstadoFacturaOrden::Enviada, $orden->estado_factura);
        Notification::assertSentTo($orden->user, FacturaEmitida::class);
    }

    public function test_un_miembro_no_puede_subir_facturas(): void
    {
        $miembro = User::factory()->miembro()->create();
        $orden   = $this->ordenConFactura();

        $this->actingAs($miembro)->post(route('caja.factura.subir', $orden), [
            'folio_fiscal' => 'X', 'pdf' => UploadedFile::fake()->create('f.pdf', 10, 'application/pdf'),
            'xml' => UploadedFile::fake()->create('f.xml', 10, 'text/xml'),
        ])->assertForbidden();
    }
}
