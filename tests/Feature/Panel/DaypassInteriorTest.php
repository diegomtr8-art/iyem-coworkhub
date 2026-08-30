<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Models\VisitanteInterior;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4.E — day-pass gratuito del interior. Decisión de Nódico: sin límite,
 * solo registro; el valor es el dato para el IYEM.
 */
class DaypassInteriorTest extends TestCase
{
    use RefreshDatabase;

    public function test_recepcion_registra_un_visitante_nuevo(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->post(route('daypass.registrar'), [
                'nombre'         => 'Rosa May Canché',
                'telefono'       => '9851110001',
                'municipio'      => 'Valladolid',
                'giro'           => 'Urdido de hamaca',
                'como_se_entero' => 'Redes sociales',
            ])
            ->assertRedirect();

        $visitante = VisitanteInterior::where('nombre', 'Rosa May Canché')->firstOrFail();
        $this->assertSame('Valladolid', $visitante->municipio);
        $this->assertSame(1, $visitante->visitas()->whereDate('fecha', today())->count());
    }

    public function test_si_vuelve_no_se_recaptura_y_suma_otra_visita(): void
    {
        $staff = User::factory()->staff()->create();
        $visitante = VisitanteInterior::create(['nombre' => 'Pedro Uc', 'municipio' => 'Tizimín']);

        // Segunda visita del mismo, referenciándolo por id: sin límite, se registra.
        $this->actingAs($staff)
            ->post(route('daypass.registrar'), ['visitante_id' => $visitante->id])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, VisitanteInterior::count());       // no se duplicó la ficha
        $this->assertSame(1, $visitante->visitas()->count());   // pero sí quedó su visita
    }

    public function test_sin_limite_puede_registrarse_muchas_veces(): void
    {
        $staff = User::factory()->staff()->create();
        $visitante = VisitanteInterior::create(['nombre' => 'Manuela Pech', 'municipio' => 'Izamal']);

        // Tres veces el mismo mes: ninguna se bloquea (decisión: sin límite).
        foreach (range(1, 3) as $_) {
            $this->actingAs($staff)
                ->post(route('daypass.registrar'), ['visitante_id' => $visitante->id])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(3, $visitante->visitas()->count());
    }

    public function test_el_buscador_encuentra_por_nombre_o_telefono(): void
    {
        VisitanteInterior::create(['nombre' => 'José Chan', 'telefono' => '9881110004', 'municipio' => 'Ticul']);

        $this->actingAs(User::factory()->staff()->create())
            ->getJson(route('daypass.buscar', ['q' => '988111']))
            ->assertOk()
            ->assertJsonPath('resultados.0.nombre', 'José Chan');
    }

    public function test_la_exportacion_neutraliza_formulas_en_los_datos(): void
    {
        // Un nombre que empieza por = no debe ejecutarse como fórmula en Excel.
        $v = VisitanteInterior::create(['nombre' => '=HYPERLINK("http://x")', 'municipio' => 'Motul']);
        $v->visitas()->create(['fecha' => today()->toDateString()]);

        $r = $this->actingAs(User::factory()->admin()->create())
            ->get(route('daypass.exportar'));

        $r->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $r->streamedContent());
    }

    public function test_un_miembro_no_entra_al_modulo(): void
    {
        $this->actingAs(User::factory()->miembro()->create())
            ->get(route('daypass.index'))
            ->assertForbidden();
    }
}
