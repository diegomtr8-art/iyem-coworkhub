<?php

namespace Tests\Feature\ApiMovil;

use App\Enums\BolsaDeHoras;
use App\Enums\MotivoMovimiento;
use App\Models\MovimientoHoras;
use App\Models\Plane;
use App\Models\Reserva;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Horas\LibroDeHoras;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las reglas de reserva se aplican **en el servidor**, aunque la app las
 * anticipe (docs/API-MOVIL.md §8, puntos 4 a 6).
 */
class ReservasApiTest extends TestCase
{
    use ApiMovil, RefreshDatabase;

    public function test_reservar_por_la_api_consume_la_bolsa_y_devuelve_el_saldo(): void
    {
        [$ana, $suscripcion] = $this->miembroCon(Plane::factory()->nodoPro()->create());
        $sala = $this->sala();

        $this->apiIdempotente('reservas', [
            'espacio_id' => $sala->id, 'fecha' => $this->diaHabil(), 'hora_inicio' => '09:00', 'hora_fin' => '11:00',
        ], $this->tokenDe($ana))
            ->assertCreated()
            ->assertJsonPath('data.reserva.horas', 2)
            ->assertJsonPath('data.reserva.cancelar_devuelve', true)
            ->assertJsonPath('data.bolsa_despues.restante', 8);

        $this->assertSame(2.0, (float) $suscripcion->refresh()->horas_sala_usadas);
    }

    public function test_sin_idempotency_key_no_se_reserva(): void
    {
        [$ana] = $this->miembroCon(Plane::factory()->nodoPro()->create());

        $this->api('POST', 'reservas', [
            'espacio_id' => $this->sala()->id, 'fecha' => $this->diaHabil(), 'hora_inicio' => '09:00', 'hora_fin' => '10:00',
        ], $this->tokenDe($ana))->assertStatus(400)->assertJsonPath('codigo', 'falta_idempotencia');

        $this->assertSame(0, Reserva::count());
    }

    public function test_la_misma_idempotency_key_no_crea_dos_reservas(): void
    {
        [$ana] = $this->miembroCon(Plane::factory()->nodoPro()->create());
        $token = $this->tokenDe($ana);
        $datos = ['espacio_id' => $this->sala()->id, 'fecha' => $this->diaHabil(), 'hora_inicio' => '09:00', 'hora_fin' => '10:00'];

        $primera = $this->apiIdempotente('reservas', $datos, $token, 'reintento-123456')->assertCreated();
        $segunda = $this->apiIdempotente('reservas', $datos, $token, 'reintento-123456')->assertCreated();

        $this->assertSame(1, Reserva::count());
        $this->assertSame($primera->json('data.reserva.id'), $segunda->json('data.reserva.id'));
        $segunda->assertHeader('Idempotent-Replayed', 'true');
    }

    public function test_el_tope_diario_se_aplica_en_el_servidor(): void
    {
        [$ana] = $this->miembroCon(Plane::factory()->nodoPro()->create());

        $this->apiIdempotente('reservas', [
            'espacio_id' => $this->sala()->id, 'fecha' => $this->diaHabil(), 'hora_inicio' => '09:00', 'hora_fin' => '12:00',
        ], $this->tokenDe($ana))
            ->assertStatus(422)
            ->assertJsonValidationErrors('hora_fin');

        $this->assertSame(0, Reserva::count());
        $this->assertSame(0, MovimientoHoras::count(), 'Un rechazo no puede tocar el libro de horas.');
    }

    public function test_bolsa_insuficiente_dice_el_numero_exacto(): void
    {
        [$ana, $suscripcion] = $this->miembroCon(Plane::factory()->nodoPro()->create());

        app(LibroDeHoras::class)->registrar(
            suscripcion: $suscripcion, bolsa: BolsaDeHoras::Sala, cantidad: 9,
            motivo: MotivoMovimiento::AjusteManual, nota: 'Consumo previo de la prueba.',
        );

        $respuesta = $this->apiIdempotente('reservas', [
            'espacio_id' => $this->sala()->id, 'fecha' => $this->diaHabil(), 'hora_inicio' => '09:00', 'hora_fin' => '11:00',
        ], $this->tokenDe($ana))->assertStatus(422);

        $this->assertStringContainsString('te quedan 1', $respuesta->json('errors.hora_fin.0'));
        $this->assertSame(0, Reserva::count());
    }

    public function test_fuera_de_horario_y_en_fin_de_semana_se_rechaza(): void
    {
        [$ana] = $this->miembroCon(Plane::factory()->nodoPro()->create());
        $token = $this->tokenDe($ana);
        $sala  = $this->sala();

        $this->apiIdempotente('reservas', [
            'espacio_id' => $sala->id, 'fecha' => $this->diaHabil(), 'hora_inicio' => '06:00', 'hora_fin' => '07:00',
        ], $token)->assertStatus(422);

        $this->apiIdempotente('reservas', [
            'espacio_id' => $sala->id, 'fecha' => $this->diaHabil(5), 'hora_inicio' => '09:00', 'hora_fin' => '10:00',
        ], $token)->assertStatus(422);

        $this->assertSame(0, Reserva::count());
    }

    public function test_sin_membresia_no_se_reserva(): void
    {
        $ana = User::factory()->miembro()->create();

        $this->apiIdempotente('reservas', [
            'espacio_id' => $this->sala()->id, 'fecha' => $this->diaHabil(), 'hora_inicio' => '09:00', 'hora_fin' => '10:00',
        ], $this->tokenDe($ana))->assertStatus(403)->assertJsonPath('codigo', 'sin_membresia');
    }

    public function test_cancelar_con_margen_devuelve_las_horas_y_una_sola_vez(): void
    {
        [$ana, $suscripcion] = $this->miembroCon(Plane::factory()->nodoPro()->create());
        $token = $this->tokenDe($ana);

        $id = $this->apiIdempotente('reservas', [
            'espacio_id' => $this->sala()->id, 'fecha' => $this->diaHabil(), 'hora_inicio' => '09:00', 'hora_fin' => '11:00',
        ], $token)->json('data.reserva.id');

        $this->api('POST', "reservas/{$id}/cancelar", token: $token)
            ->assertOk()
            ->assertJsonPath('data.devolvio_horas', true);

        $this->api('POST', "reservas/{$id}/cancelar", token: $token)
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'ya_no_confirmada');

        $this->assertSame(0.0, (float) $suscripcion->refresh()->horas_sala_usadas);
    }

    public function test_cancelar_sin_margen_no_devuelve_y_lo_dice_antes(): void
    {
        [$ana, $suscripcion] = $this->miembroCon(Plane::factory()->nodoPro()->create());

        // Reserva que empieza en una hora: dentro de la ventana de penalización.
        $inicio  = now('America/Merida')->addHour()->startOfHour();
        $reserva = Reserva::create([
            'espacio_id' => $this->sala()->id, 'user_id' => $ana->id, 'suscripcion_id' => $suscripcion->id,
            'fecha' => $inicio->toDateString(), 'hora_inicio' => $inicio->format('H:i'),
            'hora_fin' => $inicio->copy()->addHour()->format('H:i'), 'estatus' => 'Confirmada', 'precio_total' => 0,
        ]);

        $token = $this->tokenDe($ana);

        $this->api('GET', "reservas/{$reserva->id}", token: $token)->assertJsonPath('data.cancelar_devuelve', false);

        $this->api('POST', "reservas/{$reserva->id}/cancelar", token: $token)
            ->assertOk()
            ->assertJsonPath('data.devolvio_horas', false);
    }

    public function test_la_disponibilidad_trae_los_numeros_para_validar_en_vivo(): void
    {
        [$ana] = $this->miembroCon(Plane::factory()->nodoPro()->create());
        $sala  = $this->sala();
        $token = $this->tokenDe($ana);

        $this->apiIdempotente('reservas', [
            'espacio_id' => $sala->id, 'fecha' => $this->diaHabil(), 'hora_inicio' => '09:00', 'hora_fin' => '10:00',
        ], $token)->assertCreated();

        $this->api('GET', "espacios/{$sala->id}/disponibilidad/{$this->diaHabil()}", token: $token)
            ->assertOk()
            ->assertJsonPath('data.tope_diario', 2)
            ->assertJsonPath('data.usado_ese_dia', 1)
            ->assertJsonPath('data.saldo_ciclo', 9);

        $this->api('GET', "espacios/{$sala->id}/disponibilidad", token: $token)
            ->assertOk()
            ->assertJsonStructure(['data' => [['fecha', 'abierto', 'libres', 'total']]]);
    }

    /**
     * Regresión de zona horaria: el calendario es hora de Mérida. Antes se leía
     * como UTC y a las 13:00 de Mérida (19:00 UTC) la tarde salía como pasada.
     */
    public function test_a_mediodia_en_merida_la_tarde_sigue_libre_y_la_hora_sale_con_su_zona(): void
    {
        [$ana] = $this->miembroCon(Plane::factory()->nodoPro()->create());
        $sala  = $this->sala();
        $lunes = \Carbon\CarbonImmutable::parse($this->diaHabil(), 'America/Merida');

        \Carbon\CarbonImmutable::setTestNow($lunes->setTime(13, 0));
        \Illuminate\Support\Carbon::setTestNow($lunes->setTime(13, 0));

        try {
            $token  = $this->tokenDe($ana);
            $bloques = collect($this->api('GET', "espacios/{$sala->id}/disponibilidad/{$lunes->toDateString()}", token: $token)
                ->assertOk()->json('data.bloques'))->keyBy('hora');

            $this->assertSame('pasado', $bloques['12:00']['motivo']);
            $this->assertTrue($bloques['15:00']['libre'], 'A las 13:00 de Mérida, las 15:00 siguen libres.');

            $this->apiIdempotente('reservas', [
                'espacio_id' => $sala->id, 'fecha' => $lunes->toDateString(), 'hora_inicio' => '16:00', 'hora_fin' => '17:00',
            ], $token)
                ->assertCreated()
                ->assertJsonPath('data.reserva.empieza_en', $lunes->toDateString() . 'T16:00:00-06:00')
                ->assertJsonPath('data.reserva.cancelar_devuelve', true);
        } finally {
            \Carbon\CarbonImmutable::setTestNow();
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    /**
     * Regresión: entre las 18:00 y las 24:00 de Mérida, en UTC ya es mañana. El
     * «hoy» del calendario tiene que seguir siendo el de Mérida.
     */
    public function test_por_la_noche_en_merida_hoy_sigue_siendo_hoy(): void
    {
        [$ana, $suscripcion] = $this->miembroCon(Plane::factory()->nodoPro()->create());
        $sala  = $this->sala();
        $lunes = \Carbon\CarbonImmutable::parse($this->diaHabil(), 'America/Merida');

        $reserva = Reserva::create([
            'espacio_id' => $sala->id, 'user_id' => $ana->id, 'suscripcion_id' => $suscripcion->id,
            'fecha' => $lunes->toDateString(), 'hora_inicio' => '18:00', 'hora_fin' => '19:00',
            'estatus' => 'Confirmada', 'precio_total' => 0,
        ]);

        // 18:10 en Mérida = 00:10 UTC del día siguiente.
        \Carbon\CarbonImmutable::setTestNow($lunes->setTime(18, 10));
        \Illuminate\Support\Carbon::setTestNow($lunes->setTime(18, 10));

        try {
            $token = $this->tokenDe($ana);

            $this->api('GET', 'reservas?tipo=proximas', token: $token)
                ->assertOk()
                ->assertJsonPath('data.0.id', $reserva->id);

            $this->api('GET', 'inicio', token: $token)->assertJsonPath('data.proxima_reserva.id', $reserva->id);

            $this->api('GET', 'espacios', token: $token)->assertJsonPath('data.horizonte.desde', $lunes->toDateString());
        } finally {
            \Carbon\CarbonImmutable::setTestNow();
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    public function test_un_espacio_que_el_plan_no_incluye_trae_sugerencia(): void
    {
        $flex = Plane::factory()->flex()->create(['horas_sala_mes' => null]);
        Plane::factory()->nodoPro()->create();
        [$ana] = $this->miembroCon($flex);

        $this->api('GET', "espacios/{$this->sala()->id}/disponibilidad/{$this->diaHabil()}", token: $this->tokenDe($ana))
            ->assertStatus(403)
            ->assertJsonPath('codigo', 'plan_no_incluye')
            ->assertJsonPath('sugerencia.plan', 'Nodo Pro');
    }
}
