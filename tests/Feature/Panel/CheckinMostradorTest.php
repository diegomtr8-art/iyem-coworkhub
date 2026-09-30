<?php

namespace Tests\Feature\Panel;

use App\Models\Checkin;
use App\Models\Espacio;
use App\Models\Plane;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de servicio social (29-sep-2026) — «entrada, diez segundos,
 * salida: sale 0 minutos».
 *
 * Diez segundos sí son cero minutos: el cálculo está bien (de la entrada hacia
 * la salida, no al revés como en el BUG-01). Lo que confundía era enseñar un
 * cero que parece un error.
 */
class CheckinMostradorTest extends TestCase
{
    use RefreshDatabase;

    private function adentro(): array
    {
        $plan = Plane::factory()->nodoPro()->create(['horas_asesoria_mes' => 4, 'max_horas_asesoria_dia' => 1]);
        $miembro = User::factory()->miembro()->create();
        Suscripcion::factory()->delPlan($plan)->create([
            'user_id' => $miembro->id, 'fecha_inicio' => today()->subDays(5), 'fecha_fin' => today()->addMonths(3),
        ]);
        $staff = User::factory()->staff()->create();
        $espacio = Espacio::factory()->coworking()->create();

        $this->freezeSecond();
        $this->actingAs($staff)->post(route('checkins.entrada'), [
            'user_id' => $miembro->id, 'espacio_id' => $espacio->id,
        ])->assertSessionHasNoErrors();

        return [Checkin::firstOrFail(), $staff];
    }

    /** Lo que pidió el reporte antes de descartar un error de cálculo. */
    public function test_una_permanencia_de_varios_minutos_se_registra_completa(): void
    {
        [$acceso, $staff] = $this->adentro();

        $this->travel(7)->minutes();
        $this->travel(30)->seconds();
        $this->actingAs($staff)->post(route('checkins.salida', $acceso))
            ->assertSessionHas('success', 'Check-out registrado. Duración: 7 minutos.');

        $this->assertSame(7, $acceso->refresh()->duracion_minutos);
    }

    public function test_una_salida_de_segundos_no_dice_cero_minutos(): void
    {
        [$acceso, $staff] = $this->adentro();

        $this->travel(10)->seconds();
        $this->actingAs($staff)->post(route('checkins.salida', $acceso))
            ->assertSessionHas('success', 'Check-out registrado. Duración: menos de un minuto.');

        $this->assertSame(0, $acceso->refresh()->duracion_minutos);
    }

    /**
     * Directo sobre el formato: con dos horas de `travel`, la sesión del
     * personal caduca por inactividad y la petición ya no llega.
     */
    public function test_la_duracion_se_lee_en_palabras(): void
    {
        $this->assertSame('menos de un minuto', Checkin::duracionEnPalabras(0));
        $this->assertSame('1 minuto', Checkin::duracionEnPalabras(1));
        $this->assertSame('45 minutos', Checkin::duracionEnPalabras(45));
        $this->assertSame('2 h 5 min', Checkin::duracionEnPalabras(125));
        $this->assertSame('3 h', Checkin::duracionEnPalabras(180));
    }
}
