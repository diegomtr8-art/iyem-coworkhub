<?php

namespace Tests\Feature\ApiMovil;

use App\Models\Plane;
use App\Models\Reserva;
use App\Models\SolicitudAsesoria;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nodo Match (decisión del 22/09/2026): el acompañante reserva contra la misma
 * bolsa, el tope diario es por persona y cada quien solo ve y toca lo suyo. En
 * la API y en la web, porque la regla vive en el servicio.
 */
class MatchCompartidoTest extends TestCase
{
    use ApiMovil, RefreshDatabase;

    /** @return array{0: User, 1: User, 2: Suscripcion} */
    private function dupla(): array
    {
        $plan = Plane::factory()->nodoMatch()->create(['horas_asesoria_mes' => 4, 'max_horas_asesoria_dia' => 1]);
        [$titular, $suscripcion] = $this->miembroCon($plan, ['name' => 'Tita Titular']);
        $acompanante = User::factory()->miembro()->create(['name' => 'Aco Acompañante']);

        $suscripcion->update(['companion_user_id' => $acompanante->id]);

        return [$titular, $acompanante, $suscripcion->refresh()];
    }

    public function test_el_acompanante_ve_la_membresia_compartida_en_solo_lectura(): void
    {
        [, $acompanante] = $this->dupla();

        $this->api('GET', 'membresia', token: $this->tokenDe($acompanante))
            ->assertOk()
            ->assertJsonPath('data.rol_en_membresia', 'acompanante')
            ->assertJsonPath('data.titular.nombre', 'Tita Titular')
            ->assertJsonPath('data.vigente.plan.nombre', 'Nodo Match')
            ->assertJsonPath('data.vigente.precio_pagado', null)
            ->assertJsonPath('data.acompanante.admitido', false)
            ->assertJsonPath('data.renovacion', null);
    }

    public function test_los_dos_consumen_la_misma_bolsa_con_tope_diario_por_persona(): void
    {
        [$titular, $acompanante, $suscripcion] = $this->dupla();
        $sala = $this->sala();
        $dia  = $this->diaHabil();

        $this->apiIdempotente('reservas', ['espacio_id' => $sala->id, 'fecha' => $dia, 'hora_inicio' => '09:00', 'hora_fin' => '11:00'], $this->tokenDe($titular))
            ->assertCreated();

        // El mismo día, el acompañante tiene sus propias 2 h…
        $this->apiIdempotente('reservas', ['espacio_id' => $sala->id, 'fecha' => $dia, 'hora_inicio' => '11:00', 'hora_fin' => '13:00'], $this->tokenDe($acompanante))
            ->assertCreated()
            ->assertJsonPath('data.bolsa_despues.restante', 16);

        // …pero no una tercera.
        $this->apiIdempotente('reservas', ['espacio_id' => $sala->id, 'fecha' => $dia, 'hora_inicio' => '14:00', 'hora_fin' => '15:00'], $this->tokenDe($acompanante))
            ->assertStatus(422)
            ->assertJsonValidationErrors('hora_fin');

        $this->assertSame(4.0, (float) $suscripcion->refresh()->horas_sala_usadas, 'La bolsa es una sola para los dos.');
        $this->assertSame(1, Reserva::where('user_id', $acompanante->id)->where('suscripcion_id', $suscripcion->id)->count());
    }

    public function test_ninguno_ve_ni_cancela_las_reservas_del_otro(): void
    {
        [$titular, $acompanante] = $this->dupla();

        $id = $this->apiIdempotente('reservas', [
            'espacio_id' => $this->sala()->id, 'fecha' => $this->diaHabil(), 'hora_inicio' => '09:00', 'hora_fin' => '10:00',
        ], $this->tokenDe($titular))->json('data.reserva.id');

        $token = $this->tokenDe($acompanante);

        $this->api('GET', 'reservas', token: $token)->assertOk()->assertJsonCount(0, 'data');
        $this->api('GET', "reservas/{$id}", token: $token)->assertNotFound();
        $this->api('POST', "reservas/{$id}/cancelar", token: $token)->assertNotFound();
        $this->assertSame('Confirmada', Reserva::find($id)->estatus);
    }

    public function test_el_acompanante_no_gestiona_la_membresia(): void
    {
        [, $acompanante] = $this->dupla();
        $token = $this->tokenDe($acompanante);

        $this->api('POST', 'membresia/acompanante', ['email' => 'otro@correo.mx'], $token)
            ->assertStatus(403)->assertJsonPath('codigo', 'solo_titular');
        $this->api('DELETE', 'membresia/acompanante', token: $token)
            ->assertStatus(403)->assertJsonPath('codigo', 'solo_titular');
        $this->api('POST', 'membresia/renovacion/cancelar', token: $token)
            ->assertStatus(403)->assertJsonPath('codigo', 'solo_titular');
    }

    public function test_la_asesoria_del_acompanante_queda_a_su_nombre_y_su_tope_es_propio(): void
    {
        [$titular, $acompanante, $suscripcion] = $this->dupla();
        $tema = \App\Models\TemaAsesoria::create([
            'nombre' => 'Finanzas', 'categoria' => \App\Enums\CategoriaTema::cases()[0]->value, 'activo' => true,
        ]);
        $dia = $this->diaHabil();
        $datos = ['tema_id' => $tema->id, 'dia_preferido' => $dia, 'horario_preferido' => 'Por la mañana (9:00 a 12:00)'];

        $this->apiIdempotente('asesorias', $datos, $this->tokenDe($titular))->assertCreated();
        $this->apiIdempotente('asesorias', $datos, $this->tokenDe($acompanante))->assertCreated();

        $this->assertSame(1, SolicitudAsesoria::where('user_id', $acompanante->id)->where('suscripcion_id', $suscripcion->id)->count());
        $this->assertSame(1, SolicitudAsesoria::where('user_id', $titular->id)->count());
    }

    public function test_en_la_web_el_acompanante_tambien_reserva_contra_la_bolsa_compartida(): void
    {
        [, $acompanante, $suscripcion] = $this->dupla();

        $this->actingAs($acompanante)->post(route('portal.reservar.store'), [
            'espacio_id' => $this->sala()->id, 'fecha' => $this->diaHabil(), 'hora_inicio' => '09:00', 'hora_fin' => '10:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1.0, (float) $suscripcion->refresh()->horas_sala_usadas);
    }
}
