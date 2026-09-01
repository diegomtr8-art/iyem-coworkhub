<?php

namespace Tests\Feature\Acceso;

use App\Enums\AccionOperativa;
use App\Http\Controllers\Api\AccesoController;
use App\Models\EntradaBitacora;
use App\Models\EventoAcceso;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Fase 5 — la pantalla de accesos del panel y el amarre de rostros.
 *
 * Cubre lo que sostiene la operación diaria: que solo el mostrador entra, que el
 * estado del agente se reporta honestamente (un tablero en cero por agente caído
 * es peor que uno que avisa), y que vincular un rostro a un miembro respeta la
 * protección de asignación en masa y adopta su historial previo.
 */
class PanelDeAccesosTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_el_mostrador_ve_los_accesos(): void
    {
        $this->actingAs(User::factory()->miembro()->create())
            ->get(route('accesos.index'))->assertForbidden();

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('accesos.index'))->assertOk();
    }

    public function test_el_estado_del_agente_avisa_cuando_lleva_tiempo_sin_reportar(): void
    {
        Cache::forget(AccesoController::AGENTE_VISTO);

        // Nunca reportó.
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('accesos.index'))
            ->assertInertia(fn ($p) => $p->where('estadoAgente.nunca', true)->where('estadoAgente.sano', false));

        // Reportó hace 20 min → caído.
        Cache::put(AccesoController::AGENTE_VISTO, now()->subMinutes(20)->toIso8601String(), now()->addDay());
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('accesos.index'))
            ->assertInertia(fn ($p) => $p->where('estadoAgente.sano', false)->where('estadoAgente.nunca', false));

        // Reportó hace un instante → sano.
        Cache::put(AccesoController::AGENTE_VISTO, now()->toIso8601String(), now()->addDay());
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('accesos.index'))
            ->assertInertia(fn ($p) => $p->where('estadoAgente.sano', true));
    }

    public function test_vincular_amarra_el_rostro_y_adopta_los_eventos_huerfanos(): void
    {
        $staff   = User::factory()->staff()->create();
        $miembro = User::factory()->miembro()->create(['email' => 'ana@ejemplo.mx']);

        // Dos eventos reconocidos de la persona 42, aún sin dueño.
        $this->eventoDe(42, reconocido: true);
        $this->eventoDe(42, reconocido: true);

        $this->actingAs($staff)
            ->post(route('accesos.vincular'), ['person_id' => 42, 'email' => 'ANA@ejemplo.mx'])
            ->assertRedirect();

        $miembro->refresh();
        $this->assertSame(42, $miembro->smartpass_person_id);
        $this->assertSame(2, EventoAcceso::where('person_id', 42)->where('user_id', $miembro->id)->count());

        $this->assertDatabaseHas('bitacora_operacion', [
            'accion'         => AccionOperativa::VinculacionRostro->value,
            'sujeto_user_id' => $miembro->id,
        ]);
    }

    public function test_no_se_puede_robar_un_rostro_ya_vinculado(): void
    {
        $staff = User::factory()->staff()->create();
        $dueno = User::factory()->miembro()->create();
        $dueno->forceFill(['smartpass_person_id' => 7])->save();
        $otro  = User::factory()->miembro()->create(['email' => 'otro@ejemplo.mx']);

        $this->actingAs($staff)
            ->post(route('accesos.vincular'), ['person_id' => 7, 'email' => 'otro@ejemplo.mx'])
            ->assertSessionHas('error');

        $otro->refresh();
        $this->assertNull($otro->smartpass_person_id);
    }

    private function eventoDe(int $personId, bool $reconocido): EventoAcceso
    {
        return EventoAcceso::create([
            'origen_id'   => random_int(1, PHP_INT_MAX),
            'ocurrido_en' => CarbonImmutable::now(),
            'person_id'   => $personId,
            'person_type' => $reconocido ? 1 : -1,
            'reconocido'  => $reconocido,
            'pass_type'   => 0,
            'direction'   => 1,
            'device_key'  => 'PUERTA-1',
        ]);
    }
}
