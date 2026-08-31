<?php

namespace Tests\Feature\Portal;

use App\Models\BloqueoEspacio;
use App\Models\DiaFestivo;
use App\Models\Espacio;
use App\Models\Plane;
use App\Models\Reserva;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Reservas\RegistroDeBloques;
use App\Servicios\Reservas\ValidadorDeReserva;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Fase 1.4 — horario de operación, granularidad, antelación, festivos y bloqueos.
 */
class ReglasDeCalendarioTest extends TestCase
{
    use RefreshDatabase;

    private function lunes(int $sumar = 0): string
    {
        return today()->next(Carbon::MONDAY)->addDays($sumar)->toDateString();
    }

    /** @return array{0: User, 1: Suscripcion, 2: Espacio} */
    private function miembroConNodoPro(): array
    {
        $plan = Plane::factory()->nodoPro()->create();
        $user = User::factory()->miembro()->create();

        $suscripcion = Suscripcion::factory()->delPlan($plan)->create([
            'user_id'      => $user->id,
            'fecha_inicio' => today()->subDays(5),
            'fecha_fin'    => today()->addMonths(6),
        ]);

        return [$user, $suscripcion, Espacio::factory()->salaJuntas()->create()];
    }

    private function reservar(User $user, Espacio $espacio, string $fecha, string $inicio, string $fin)
    {
        return $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id'  => $espacio->id,
            'fecha'       => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin'    => $fin,
        ]);
    }

    // ── Horario de operación ────────────────────────────────────────────────

    public function test_no_se_puede_reservar_antes_de_la_apertura(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();

        $this->reservar($user, $sala, $this->lunes(), '08:00', '09:00')
            ->assertSessionHasErrors('hora_inicio');

        $this->assertSame(0, Reserva::count());
    }

    public function test_una_reserva_no_puede_cruzar_el_cierre(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();

        // Cierra a las 19:00; de 18:00 a 20:00 se queda alguien dentro.
        $this->reservar($user, $sala, $this->lunes(), '18:00', '20:00')
            ->assertSessionHasErrors('hora_fin');

        $this->assertSame(0, Reserva::count());
    }

    public function test_una_reserva_que_acaba_justo_al_cierre_si_vale(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();

        $this->reservar($user, $sala, $this->lunes(), '18:00', '19:00')
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Reserva::count());
    }

    public function test_no_se_puede_reservar_en_fin_de_semana(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();
        $sabado = today()->next(Carbon::SATURDAY)->toDateString();

        $this->reservar($user, $sala, $sabado, '10:00', '11:00')
            ->assertSessionHasErrors('fecha');
    }

    /** Un espacio puede tener su propio horario, y manda sobre el general. */
    public function test_un_espacio_con_horario_propio_manda_sobre_el_general(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();

        $sala->update(['hora_apertura' => '11:00:00', 'hora_cierre' => '15:00:00']);

        $this->reservar($user, $sala, $this->lunes(), '10:00', '11:00')
            ->assertSessionHasErrors('hora_inicio');

        $this->reservar($user, $sala, $this->lunes(), '11:00', '12:00')
            ->assertSessionHasNoErrors();
    }

    // ── Granularidad y duración ─────────────────────────────────────────────

    public function test_las_reservas_van_en_bloques_de_media_hora(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();

        $this->reservar($user, $sala, $this->lunes(), '09:15', '10:15')
            ->assertSessionHasErrors('hora_inicio');
    }

    public function test_la_reserva_minima_es_de_una_hora(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();

        $this->reservar($user, $sala, $this->lunes(), '09:00', '09:30')
            ->assertSessionHasErrors('hora_fin');
    }

    // ── Antelación ──────────────────────────────────────────────────────────

    public function test_no_se_reserva_con_mas_de_tres_meses_de_antelacion(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();

        $lejos = today()->addMonths(4)->next(Carbon::MONDAY)->toDateString();

        $this->reservar($user, $sala, $lejos, '10:00', '11:00')
            ->assertSessionHasErrors('fecha');
    }

    public function test_el_operativo_puede_saltarse_la_antelacion_minima_pero_no_el_horario(): void
    {
        $validador = app(ValidadorDeReserva::class);
        $sala      = Espacio::factory()->salaJuntas()->create();

        $lunes = CarbonImmutable::parse($this->lunes());
        $ahora = $lunes->setTime(10, 0);

        // Como miembro, para dentro de 5 minutos: demasiado justo.
        try {
            $validador->validar($sala, $lunes->toDateString(), '10:00', '11:00', false, $ahora->addMinutes(-5));
            $this->fail('Debió rechazar la antelación mínima.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('hora_inicio', $e->errors());
        }

        // Como recepción, con alguien enfrente: pasa.
        $validador->validar($sala, $lunes->toDateString(), '10:00', '11:00', true, $ahora->addMinutes(-5));

        // Pero recepción tampoco abre el local un domingo.
        $domingo = CarbonImmutable::parse($this->lunes())->subDay();

        $this->expectException(ValidationException::class);
        $validador->validar($sala, $domingo->toDateString(), '10:00', '11:00', true, $ahora);
    }

    // ── Festivos ────────────────────────────────────────────────────────────

    public function test_no_se_reserva_un_dia_festivo(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();
        $lunes = $this->lunes();

        DiaFestivo::create([
            'fecha'               => $lunes,
            'nombre'              => 'Día de la Constitución',
            'cerrado_todo_el_dia' => true,
        ]);

        $this->reservar($user, $sala, $lunes, '10:00', '11:00')
            ->assertSessionHasErrors('fecha');
    }

    public function test_un_cierre_parcial_recorta_la_franja_pero_no_la_amplia(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();
        $lunes = $this->lunes();

        DiaFestivo::create([
            'fecha'               => $lunes,
            'nombre'              => 'Nochebuena',
            'cerrado_todo_el_dia' => false,
            'hora_apertura'       => '08:00:00',
            'hora_cierre'         => '14:00:00',
        ]);

        // El cierre adelantado sí aplica.
        $this->reservar($user, $sala, $lunes, '14:00', '15:00')
            ->assertSessionHasErrors('hora_fin');

        // Pero la apertura anticipada del festivo no abre antes de las 9.
        $this->reservar($user, $sala, $lunes, '08:00', '09:00')
            ->assertSessionHasErrors('hora_inicio');

        // Y dentro de la franja recortada, todo normal.
        $this->reservar($user, $sala, $lunes, '10:00', '11:00')
            ->assertSessionHasNoErrors();
    }

    // ── Bloqueos ────────────────────────────────────────────────────────────

    /**
     * Un bloqueo compite con las reservas por la misma tabla de bloques, así
     * que el índice único los enfrenta sin código que compare unos con otros.
     */
    public function test_un_bloqueo_por_mantenimiento_impide_reservar_ese_horario(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();
        $lunes = $this->lunes();

        $bloqueo = BloqueoEspacio::create([
            'espacio_id'  => $sala->id,
            'fecha'       => $lunes,
            'hora_inicio' => '10:00:00',
            'hora_fin'    => '12:00:00',
            'motivo'      => 'Mantenimiento del aire acondicionado',
        ]);

        app(RegistroDeBloques::class)->ocuparBloqueo($bloqueo);

        // Dentro del bloqueo: no se puede.
        $this->reservar($user, $sala, $lunes, '10:00', '11:00')
            ->assertSessionHasErrors();

        $this->assertSame(0, Reserva::where('estatus', 'Confirmada')->count());

        // Justo después del bloqueo: sí.
        $this->reservar($user, $sala, $lunes, '12:00', '13:00')
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Reserva::where('estatus', 'Confirmada')->count());
    }

    public function test_liberar_un_bloqueo_devuelve_el_horario(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();
        $lunes    = $this->lunes();
        $registro = app(RegistroDeBloques::class);

        $bloqueo = BloqueoEspacio::create([
            'espacio_id'  => $sala->id,
            'fecha'       => $lunes,
            'hora_inicio' => '10:00:00',
            'hora_fin'    => '12:00:00',
            'motivo'      => 'Evento privado que se canceló',
        ]);

        $registro->ocuparBloqueo($bloqueo);
        $registro->liberarBloqueo($bloqueo);

        $this->reservar($user, $sala, $lunes, '10:00', '11:00')
            ->assertSessionHasNoErrors();
    }

    // ── Franja del día, la que pinta la pantalla de reservar ────────────────

    public function test_la_franja_del_dia_dice_cuando_abre_cada_espacio(): void
    {
        $validador = app(ValidadorDeReserva::class);
        $sala      = Espacio::factory()->salaJuntas()->create();

        $lunes   = CarbonImmutable::parse($this->lunes());
        $domingo = $lunes->subDay();

        $this->assertSame(['09:00', '19:00'], $validador->franjaDelDia($sala, $lunes));
        $this->assertNull($validador->franjaDelDia($sala, $domingo), 'El domingo no abre.');
        $this->assertTrue($validador->abreEse($sala, $lunes));
        $this->assertFalse($validador->abreEse($sala, $domingo));
    }
}
