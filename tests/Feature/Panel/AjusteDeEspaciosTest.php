<?php

namespace Tests\Feature\Panel;

use App\Enums\TipoEspacio;
use App\Models\Espacio;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4.B — la migración que lleva el catálogo de espacios a la realidad de
 * Nódico tiene que ajustar **datos que ya existen** sin llevarse una sola
 * reserva por delante. La FK es `cascadeOnDelete`, así que un borrado
 * descuidado sería silencioso y definitivo; esto lo blinda.
 */
class AjusteDeEspaciosTest extends TestCase
{
    use RefreshDatabase;

    /** Corre la migración de ajuste sobre el estado actual de la base. */
    private function correrAjuste(): void
    {
        (require database_path('migrations/2026_08_30_100001_ajustar_espacios_a_los_reales.php'))->up();
    }

    private function estadoViejo(): void
    {
        // Deja la base como el seeder anterior: 5 cubículos y 1 sola sala.
        Espacio::query()->delete();
        foreach (range(1, 5) as $n) {
            Espacio::factory()->privado()->create(['nombre' => "Cubículo Privado {$n}"]);
        }
        Espacio::factory()->salaJuntas()->create(['nombre' => 'Sala de Juntas Nodico']);
    }

    public function test_deja_cuatro_cubiculos_y_dos_salas_de_juntas(): void
    {
        $this->estadoViejo();

        $this->correrAjuste();

        $this->assertSame(4, Espacio::where('tipo', TipoEspacio::Privado->value)->count());
        $this->assertSame(2, Espacio::where('tipo', TipoEspacio::SalaJuntas->value)->count());
    }

    public function test_el_cubiculo_sobrante_vacio_se_elimina(): void
    {
        $this->estadoViejo();

        $this->correrAjuste();

        // El de id más alto era el quinto y estaba vacío: fuera.
        $this->assertDatabaseMissing('espacios', ['nombre' => 'Cubículo Privado 5']);
    }

    public function test_un_cubiculo_sobrante_con_reservas_no_se_borra_sino_que_se_desactiva(): void
    {
        $this->estadoViejo();

        // El quinto cubículo —el que sobra— tiene una reserva colgando.
        $quinto = Espacio::where('nombre', 'Cubículo Privado 5')->first();
        $reserva = Reserva::factory()->create([
            'espacio_id' => $quinto->id,
            'user_id'    => User::factory()->miembro(),
        ]);

        $this->correrAjuste();

        // Ni el espacio ni la reserva desaparecen: la cascada no se dispara.
        $this->assertDatabaseHas('espacios', ['id' => $quinto->id, 'disponible' => false]);
        $this->assertDatabaseHas('reservas', ['id' => $reserva->id]);
        // Y siguen siendo 4 los cubículos activos.
        $this->assertSame(4, Espacio::where('tipo', TipoEspacio::Privado->value)
            ->where('disponible', true)->count());
    }

    public function test_es_idempotente(): void
    {
        $this->estadoViejo();

        $this->correrAjuste();
        $this->correrAjuste(); // segunda pasada: no debe cambiar nada.

        $this->assertSame(4, Espacio::where('tipo', TipoEspacio::Privado->value)->count());
        $this->assertSame(2, Espacio::where('tipo', TipoEspacio::SalaJuntas->value)->count());
    }

    public function test_no_toca_una_base_vacia_porque_de_eso_se_encarga_el_seeder(): void
    {
        Espacio::query()->delete();

        $this->correrAjuste();

        $this->assertSame(0, Espacio::count());
    }
}
