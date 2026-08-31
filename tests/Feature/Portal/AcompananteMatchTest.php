<?php

namespace Tests\Feature\Portal;

use App\Models\Plane;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nodo Match — el miembro asigna a su acompañante por correo desde el portal.
 *
 * Regla (decisión de Diego): el acompañante debe ser una cuenta registrada, con
 * el correo verificado y sin suspender; no necesita membresía propia. Si no, sale
 * la alerta y no se asigna.
 */
class AcompananteMatchTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Suscripcion} */
    private function titularConMatch(): array
    {
        $plan = Plane::factory()->nodoMatch()->create();
        $user = User::factory()->miembro()->create();

        $suscripcion = Suscripcion::factory()->delPlan($plan)->create([
            'user_id'      => $user->id,
            'fecha_inicio' => today()->subDays(5),
            'fecha_fin'    => today()->addMonth(),
        ]);

        return [$user, $suscripcion];
    }

    public function test_asigna_al_acompanante_con_correo_registrado_y_verificado(): void
    {
        [$titular, $suscripcion] = $this->titularConMatch();
        $companion = User::factory()->miembro()->create([
            'email' => 'ana@correo.mx', 'email_verified_at' => now(),
        ]);

        $this->actingAs($titular)
            ->post(route('portal.membresia.acompanante'), ['email' => 'ANA@correo.mx']) // sin importar mayúsculas
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('suscripciones', [
            'id'                => $suscripcion->id,
            'companion_user_id' => $companion->id,
        ]);
    }

    public function test_un_correo_sin_cuenta_no_se_puede_asignar(): void
    {
        [$titular] = $this->titularConMatch();

        $this->actingAs($titular)
            ->post(route('portal.membresia.acompanante'), ['email' => 'nadie@correo.mx'])
            ->assertSessionHasErrors('email');
    }

    public function test_un_correo_sin_verificar_no_se_puede_asignar(): void
    {
        [$titular] = $this->titularConMatch();
        User::factory()->miembro()->create(['email' => 'sinver@correo.mx', 'email_verified_at' => null]);

        $this->actingAs($titular)
            ->post(route('portal.membresia.acompanante'), ['email' => 'sinver@correo.mx'])
            ->assertSessionHasErrors('email');
    }

    public function test_no_puede_asignarse_a_si_mismo(): void
    {
        [$titular] = $this->titularConMatch();

        $this->actingAs($titular)
            ->post(route('portal.membresia.acompanante'), ['email' => $titular->email])
            ->assertSessionHasErrors('email');
    }

    public function test_un_plan_de_una_persona_no_admite_acompanante(): void
    {
        $plan = Plane::factory()->nodoPro()->create();
        $user = User::factory()->miembro()->create();
        Suscripcion::factory()->delPlan($plan)->create([
            'user_id' => $user->id, 'fecha_inicio' => today(), 'fecha_fin' => today()->addMonth(),
        ]);
        User::factory()->miembro()->create(['email' => 'otra@correo.mx', 'email_verified_at' => now()]);

        $this->actingAs($user)
            ->post(route('portal.membresia.acompanante'), ['email' => 'otra@correo.mx'])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('suscripciones', ['user_id' => $user->id, 'companion_user_id' => null]);
    }

    public function test_se_puede_quitar_al_acompanante(): void
    {
        [$titular, $suscripcion] = $this->titularConMatch();
        $companion = User::factory()->miembro()->create(['email_verified_at' => now()]);
        $suscripcion->update(['companion_user_id' => $companion->id]);

        $this->actingAs($titular)
            ->delete(route('portal.membresia.acompanante.quitar'))
            ->assertRedirect();

        $this->assertDatabaseHas('suscripciones', [
            'id' => $suscripcion->id, 'companion_user_id' => null,
        ]);
    }
}
