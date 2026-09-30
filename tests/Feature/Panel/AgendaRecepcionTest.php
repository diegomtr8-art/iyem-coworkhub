<?php

namespace Tests\Feature\Panel;

use App\Models\Espacio;
use App\Models\Plane;
use App\Models\Reserva;
use App\Models\Suscripcion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Pruebas de servicio social (29-sep-2026) — «No pude crear la reserva de
 * horario, me pedía un user id el cual no se muestra en ninguna parte».
 *
 * Dos causas: el formulario de recepción mandaba `user_id` vacío si se
 * escribía el nombre sin elegirlo de la lista, y la validación contestaba con
 * el nombre crudo del campo; y mover una reserva tenía ruta pero ninguna
 * pantalla.
 */
class AgendaRecepcionTest extends TestCase
{
    use RefreshDatabase;

    private function reservaDeLunes(): array
    {
        $plan = Plane::factory()->nodoPro()->create(['horas_asesoria_mes' => 4, 'max_horas_asesoria_dia' => 1]);
        $miembro = User::factory()->miembro()->create();
        Suscripcion::factory()->delPlan($plan)->create([
            'user_id' => $miembro->id, 'fecha_inicio' => today()->subDays(5), 'fecha_fin' => today()->addMonths(3),
        ]);
        $sala = Espacio::factory()->salaJuntas()->create();
        $staff = User::factory()->staff()->create();
        $lunes = today()->next(Carbon::MONDAY)->toDateString();

        $this->actingAs($staff)->post(route('agenda.reservar'), [
            'user_id' => $miembro->id, 'espacio_id' => $sala->id, 'fecha' => $lunes,
            'hora_inicio' => '10:00', 'hora_fin' => '11:00',
        ])->assertSessionHasNoErrors();

        return [Reserva::firstOrFail(), $staff, $sala, $lunes];
    }

    public function test_reservar_sin_elegir_a_la_persona_explica_que_hacer_sin_hablar_de_ids(): void
    {
        $sala = Espacio::factory()->salaJuntas()->create();

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('agenda.reservar'), [
                'user_id' => '', 'espacio_id' => $sala->id, 'fecha' => today()->next(Carbon::MONDAY)->toDateString(),
                'hora_inicio' => '10:00', 'hora_fin' => '11:00',
            ])
            ->assertSessionHasErrors(['user_id' => 'Busca a la persona por nombre, correo o teléfono y elígela de la lista.']);
    }

    /** La pantalla necesita saber en qué espacio está cada reserva para moverla. */
    public function test_la_agenda_da_lo_necesario_para_mover_una_reserva(): void
    {
        [$reserva, $staff, $sala, $lunes] = $this->reservaDeLunes();

        $this->actingAs($staff)
            ->get(route('agenda.index', ['semana' => $lunes]))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('dias', fn ($dias) => collect($dias)
                    ->flatMap(fn ($d) => $d['columnas'])
                    ->flatMap(fn ($c) => $c['eventos'])
                    ->contains(fn ($e) => $e['tipo'] === 'reserva' && $e['id'] === $reserva->id && $e['espacio_id'] === $sala->id)));
    }

    public function test_recepcion_mueve_una_reserva_de_horario(): void
    {
        [$reserva, $staff, $sala, $lunes] = $this->reservaDeLunes();

        $this->actingAs($staff)->patch(route('agenda.mover', $reserva), [
            'espacio_id' => $sala->id, 'fecha' => $lunes, 'hora_inicio' => '12:00', 'hora_fin' => '13:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame('12:00', substr($reserva->refresh()->hora_inicio, 0, 5));
    }
}
