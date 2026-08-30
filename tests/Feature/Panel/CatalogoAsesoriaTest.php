<?php

namespace Tests\Feature\Panel;

use App\Models\Asesor;
use App\Models\Plane;
use App\Models\SolicitudAsesoria;
use App\Models\Suscripcion;
use App\Models\TemaAsesoria;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4.D — catálogo de temas y colaboradores de asesoría, y la oferta en el
 * portal.
 */
class CatalogoAsesoriaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_crea_un_tema(): void
    {
        $this->actingAs($this->admin())
            ->post(route('temas.store'), [
                'nombre'       => 'Modelo de negocio',
                'categoria'    => 'basicos',
                'duracion_min' => 60,
                'activo'       => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('temas_asesoria', ['nombre' => 'Modelo de negocio', 'categoria' => 'basicos']);
    }

    public function test_recepcion_no_toca_el_catalogo_de_temas(): void
    {
        // El catálogo es configuración: solo administración.
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('temas.index'))
            ->assertForbidden();
    }

    public function test_un_asesor_se_guarda_con_sus_temas(): void
    {
        $t1 = TemaAsesoria::create(['nombre' => 'Ventas', 'categoria' => 'basicos', 'duracion_min' => 60]);
        $t2 = TemaAsesoria::create(['nombre' => 'Comercio electrónico', 'categoria' => 'especializados', 'duracion_min' => 60]);

        $this->actingAs($this->admin())
            ->post(route('asesores.store'), [
                'nombre'    => 'Lucía Fernández',
                'semblanza' => 'Contadora con 10 años de experiencia.',
                'activo'    => true,
                'temas'     => [$t1->id, $t2->id],
            ])
            ->assertRedirect();

        $asesor = Asesor::where('nombre', 'Lucía Fernández')->firstOrFail();
        $this->assertEqualsCanonicalizing([$t1->id, $t2->id], $asesor->temas->pluck('id')->all());
    }

    public function test_el_portal_muestra_la_oferta_agrupada_por_categoria(): void
    {
        [$miembro] = $this->miembroConAsesoria();
        $tema = TemaAsesoria::create(['nombre' => 'Finanzas básicas', 'categoria' => 'basicos', 'duracion_min' => 60]);
        $asesor = Asesor::create(['nombre' => 'Marco Peña', 'activo' => true]);
        $asesor->temas()->attach($tema->id);

        $this->actingAs($miembro)
            ->get(route('portal.asesoria'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('incluida', true)
                ->has('oferta')
            );
    }

    public function test_solicitar_desde_el_portal_guarda_el_asesor_preferido(): void
    {
        [$miembro, $suscripcion] = $this->miembroConAsesoria();
        $tema = TemaAsesoria::create(['nombre' => 'Ventas', 'categoria' => 'basicos', 'duracion_min' => 60]);
        $asesor = Asesor::create(['nombre' => 'Sofía Canul', 'activo' => true]);

        $this->actingAs($miembro)
            ->post(route('portal.asesoria.store'), [
                'tema_id'             => $tema->id,
                'asesor_preferido_id' => $asesor->id,
                'dia_preferido'       => CarbonImmutable::today()->addDays(3)->toDateString(),
                'horario_preferido'   => 'Por la mañana',
            ])
            ->assertRedirect();

        $solicitud = SolicitudAsesoria::where('user_id', $miembro->id)->firstOrFail();
        $this->assertSame($asesor->id, $solicitud->asesor_preferido_id);
        $this->assertStringContainsString('Ventas', $solicitud->tema);
    }

    /** @return array{0: User, 1: Suscripcion} */
    private function miembroConAsesoria(): array
    {
        $plan = Plane::create([
            'nombre' => 'Nodo Pro', 'tipo' => 'mes', 'precio' => 599,
            'horas_asesoria_mes' => 4, 'max_horas_asesoria_dia' => 1, 'activo' => true,
        ]);
        $miembro = User::factory()->miembro()->create();
        $suscripcion = Suscripcion::create([
            'user_id' => $miembro->id, 'plan_id' => $plan->id,
            'fecha_inicio' => CarbonImmutable::today()->subDays(5)->toDateString(),
            'fecha_fin' => CarbonImmutable::today()->addDays(25)->toDateString(),
            'estatus' => 'Activa', 'precio_pagado' => 599,
        ]);

        return [$miembro, $suscripcion];
    }
}
