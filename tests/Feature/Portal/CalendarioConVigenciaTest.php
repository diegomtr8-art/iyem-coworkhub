<?php

namespace Tests\Feature\Portal;

use App\Models\Espacio;
use App\Models\Plane;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ApiMovil\ApiMovil;
use Tests\TestCase;

/**
 * Pruebas de servicio social (29-sep-2026) — «nadie puede reservar una sala».
 *
 * Causa: la membresía demo había vencido pero seguía «Activa»; el rango del
 * mes quedaba al revés (hoy > su último día) y `/portal/disponibilidad`
 * respondía 200 con `dias: []`. El calendario se quedaba sin días y sin una
 * palabra. Lo mismo en asesoría: el selector de fecha tenía el mínimo después
 * del máximo.
 *
 * Lo que se comprueba: que la pantalla **dice** qué pasa (vencida, por
 * vencer, plan que no incluye, espacio no reservable) en vez de callarse.
 */
class CalendarioConVigenciaTest extends TestCase
{
    use ApiMovil;
    use RefreshDatabase;

    private function miembro(string $inicio, string $fin): array
    {
        $plan = Plane::factory()->nodoPro()->create(['horas_asesoria_mes' => 4, 'max_horas_asesoria_dia' => 1, 'activo' => true]);
        $user = User::factory()->miembro()->create();
        Suscripcion::factory()->delPlan($plan)->create([
            'user_id' => $user->id, 'fecha_inicio' => $inicio, 'fecha_fin' => $fin,
        ]);

        return [$user, $plan, Espacio::factory()->salaJuntas()->create()];
    }

    private function vencido(): array
    {
        return $this->miembro(today()->subMonths(2)->toDateString(), today()->subDays(19)->toDateString());
    }

    // ── Reservar ────────────────────────────────────────────────────────────

    public function test_con_la_membresia_vencida_la_disponibilidad_explica_en_vez_de_devolver_un_mes_vacio(): void
    {
        [$user, , $sala] = $this->vencido();

        $this->actingAs($user)
            ->getJson(route('portal.disponibilidad', ['espacio_id' => $sala->id, 'fecha' => today()->toDateString(), 'mes' => 1]))
            ->assertStatus(409)
            ->assertJsonPath('motivo', 'membresia_vencida')
            ->assertJson(fn ($json) => $json->where('mensaje', fn ($m) => str_contains($m, 'venció') && str_contains($m, 'Renuévala'))->etc());
    }

    public function test_la_pantalla_de_reservar_sabe_que_la_membresia_vencio_y_como_renovar(): void
    {
        [$user, $plan] = $this->vencido();

        $this->actingAs($user)->get(route('portal.reservar'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Portal/Reservar')
                ->where('vigencia.vencida', true)
                ->where('vigencia.mensaje', fn ($m) => str_contains($m, 'venció'))
                ->where('renovarA', route('portal.contratar', $plan)));
    }

    public function test_si_la_membresia_vence_antes_del_horizonte_se_avisa_hasta_cuando_se_puede_reservar(): void
    {
        [$user] = $this->miembro(today()->subDays(20)->toDateString(), today()->addDays(14)->toDateString());

        $this->actingAs($user)->get(route('portal.reservar'))
            ->assertInertia(fn ($p) => $p
                ->where('vigencia.vencida', false)
                ->where('vigencia.limita', true)
                ->where('vigencia.mensaje', fn ($m) => str_contains($m, 'no puedes reservar después')));
    }

    public function test_con_la_membresia_lejos_de_vencer_no_hay_aviso(): void
    {
        [$user] = $this->miembro(today()->subDays(5)->toDateString(), today()->addYear()->toDateString());

        $this->actingAs($user)->get(route('portal.reservar'))
            ->assertInertia(fn ($p) => $p->where('vigencia.limita', false)->where('vigencia.mensaje', null));
    }

    public function test_los_rechazos_de_la_disponibilidad_traen_su_mensaje(): void
    {
        [$user, , $sala] = $this->miembro(today()->subDays(5)->toDateString(), today()->addYear()->toDateString());
        $coworking = Espacio::factory()->coworking()->create();

        $this->actingAs($user)
            ->getJson(route('portal.disponibilidad', ['espacio_id' => $coworking->id, 'fecha' => today()->toDateString()]))
            ->assertNotFound()
            ->assertJsonPath('motivo', 'no_reservable');

        $sinMembresia = User::factory()->miembro()->create();
        $this->actingAs($sinMembresia)
            ->getJson(route('portal.disponibilidad', ['espacio_id' => $sala->id, 'fecha' => today()->toDateString()]))
            ->assertForbidden()
            ->assertJsonPath('motivo', 'sin_membresia');
    }

    // ── Asesoría ────────────────────────────────────────────────────────────

    public function test_la_asesoria_sabe_que_la_membresia_vencio(): void
    {
        [$user, $plan] = $this->vencido();

        $this->actingAs($user)->get(route('portal.asesoria'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Portal/Asesoria')
                ->where('vencida', true)
                ->where('renovarA', route('portal.contratar', $plan)));
    }

    // ── App ─────────────────────────────────────────────────────────────────

    public function test_la_app_recibe_un_error_claro_con_la_membresia_vencida(): void
    {
        [$user, , $sala] = $this->vencido();
        $token = $this->tokenDe($user);

        $this->api('GET', "espacios/{$sala->id}/disponibilidad?fecha=".today()->toDateString(), [], $token)
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'membresia_vencida');

        $this->api('GET', 'asesorias', [], $token)->assertJsonPath('data.vencida', true);
    }
}
