<?php

namespace Tests\Feature\Pagos;

use App\Enums\EstadoFacturaOrden;
use App\Enums\EstadoPagoOrden;
use App\Enums\MetodoReferencia;
use App\Models\DatosFiscales;
use App\Models\OrdenPago;
use App\Models\Plane;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 1 — modelo de la orden de pago con referencia.
 */
class OrdenPagoReferenciaTest extends TestCase
{
    use RefreshDatabase;

    private function ordenBase(array $extra = []): OrdenPago
    {
        $user = User::factory()->miembro()->create();
        $plan = Plane::factory()->nodoPro()->create();
        $ref  = OrdenPago::nuevaReferencia();

        return OrdenPago::create(array_merge([
            'referencia'             => $ref['referencia'],
            'referencia_normalizada' => $ref['referencia_normalizada'],
            'user_id'                => $user->id,
            'plan_id'                => $plan->id,
            'monto'                  => 599,
            'metodo'                 => MetodoReferencia::Transferencia,
            'pide_factura'           => true,
            'vence_el'               => now()->addDays(7),
        ], $extra));
    }

    public function test_la_referencia_no_usa_caracteres_ambiguos(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $ref = OrdenPago::nuevaReferencia()['referencia'];
            $this->assertMatchesRegularExpression('/^NDC-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{6}$/', $ref);
            foreach (['0', 'O', '1', 'I', 'L'] as $ambiguo) {
                $this->assertStringNotContainsString($ambiguo, substr($ref, 4), "La referencia $ref trae un carácter ambiguo.");
            }
        }
    }

    public function test_la_normalizacion_tolera_el_formato_del_banco(): void
    {
        $this->assertSame('NDC7K4M2Q', OrdenPago::normalizar('ndc-7k4m2q'));
        $this->assertSame('NDC7K4M2Q', OrdenPago::normalizar('NDC 7K4M2Q'));
        $this->assertSame('NDC7K4M2Q', OrdenPago::normalizar('  NDC-7K4M2Q  '));
    }

    public function test_se_encuentra_aunque_venga_deformada(): void
    {
        $orden = $this->ordenBase();
        $deformada = ' ' . strtolower(str_replace('-', '', $orden->referencia)) . ' ';

        $encontrada = OrdenPago::query()->buscarReferencia($deformada)->first();

        $this->assertNotNull($encontrada);
        $this->assertTrue($encontrada->is($orden));
    }

    public function test_los_estados_son_dos_ciclos_separados(): void
    {
        $orden = $this->ordenBase();

        $this->assertSame(EstadoPagoOrden::Generada, $orden->estado_pago);
        $this->assertSame(EstadoFacturaOrden::NoSolicitada, $orden->estado_factura);
        $this->assertTrue($orden->estado_pago->estaAbierta());
    }

    public function test_la_foto_fiscal_no_cambia_si_el_miembro_edita_su_rfc_despues(): void
    {
        $orden = $this->ordenBase();

        $df = DatosFiscales::create([
            'user_id'          => $orden->user_id,
            'rfc'              => 'XAXX010101000',
            'razon_social'     => 'Emprendedora Demo SA de CV',
            'regimen_fiscal'   => '601',
            'uso_cfdi'         => 'G03',
            'codigo_postal'    => '97000',
            'email_facturacion' => 'facturas@demo.mx',
        ]);

        $orden->copiarFiscalesDe($df);
        $orden->save();

        // El miembro cambia su RFC dos semanas después.
        $df->update(['rfc' => 'XEXX010101000']);

        $this->assertSame('XAXX010101000', $orden->fresh()->fiscal_rfc, 'La orden reescribió la historia.');
        $this->assertSame('Emprendedora Demo SA de CV', $orden->fresh()->fiscal_razon_social);
    }
}
