<?php

namespace Tests\Feature\Portal;

use App\Enums\BolsaDeHoras;
use App\Enums\MotivoMovimiento;
use App\Models\Espacio;
use App\Models\MovimientoHoras;
use App\Models\Plane;
use App\Models\Reserva;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Horas\LibroDeHoras;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Fase 1.1, 1.5 y 1.6 — el libro de horas, el ciclo de aniversario y el no-show.
 */
class LibroDeHorasTest extends TestCase
{
    use RefreshDatabase;

    private LibroDeHoras $libro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->libro = app(LibroDeHoras::class);
    }

    private function nodoProDesde(string $fechaInicio, int $meses = 12): Suscripcion
    {
        $plan = Plane::factory()->nodoPro()->create();
        $user = User::factory()->miembro()->create();

        return Suscripcion::factory()->delPlan($plan)->create([
            'user_id'      => $user->id,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => CarbonImmutable::parse($fechaInicio)->addMonths($meses)->toDateString(),
        ])->load('plan');
    }

    // ── Ciclo de aniversario ────────────────────────────────────────────────

    /** Quien contrata el 15 de marzo tiene sus horas del 15 de marzo al 14 de abril. */
    public function test_el_ciclo_va_por_aniversario_no_por_mes_natural(): void
    {
        $suscripcion = $this->nodoProDesde('2026-03-15');

        $enMarzo = CarbonImmutable::parse('2026-03-20');
        $this->assertSame('2026-03-15', $suscripcion->cicloInicio($enMarzo)->toDateString());
        $this->assertSame('2026-04-14', $suscripcion->cicloFin($enMarzo)->toDateString());

        // Un día antes del aniversario sigue en el ciclo anterior.
        $vispera = CarbonImmutable::parse('2026-04-14');
        $this->assertSame('2026-03-15', $suscripcion->cicloInicio($vispera)->toDateString());

        // Y el mismo día del aniversario ya es el ciclo nuevo.
        $aniversario = CarbonImmutable::parse('2026-04-15');
        $this->assertSame('2026-04-15', $suscripcion->cicloInicio($aniversario)->toDateString());
        $this->assertSame('2026-05-14', $suscripcion->cicloFin($aniversario)->toDateString());
    }

    /**
     * El caso que rompe las implementaciones ingenuas: con `addMonths` a secas,
     * el 31 de enero salta al 3 de marzo y el miembro pierde tres días de ciclo.
     */
    public function test_una_suscripcion_de_fin_de_mes_no_se_desborda_en_febrero(): void
    {
        $suscripcion = $this->nodoProDesde('2026-01-31');

        $enFebrero = CarbonImmutable::parse('2026-02-28');

        $this->assertSame(
            '2026-02-28',
            $suscripcion->cicloInicio($enFebrero)->toDateString(),
            'El ciclo de febrero debe caer en el último día del mes, no desbordarse a marzo.'
        );
    }

    /** Day-Pass y Flex no tienen ciclos: su bolsa es la de toda la suscripción. */
    public function test_un_plan_por_dias_no_tiene_ciclos_mensuales(): void
    {
        $plan = Plane::factory()->flex()->create();
        $user = User::factory()->miembro()->create();

        $suscripcion = Suscripcion::factory()->delPlan($plan)->create([
            'user_id'      => $user->id,
            'fecha_inicio' => '2026-03-15',
            'fecha_fin'    => '2026-04-14',
        ])->load('plan');

        $enAbril = CarbonImmutable::parse('2026-04-10');

        $this->assertSame('2026-03-15', $suscripcion->cicloInicio($enAbril)->toDateString());
        $this->assertNull($suscripcion->proximoReinicio($enAbril));
    }

    /** Lo no usado se pierde: el consumo del ciclo anterior no viaja al nuevo. */
    public function test_las_horas_no_se_acumulan_entre_ciclos(): void
    {
        $suscripcion = $this->nodoProDesde('2026-03-15');
        $enMarzo     = CarbonImmutable::parse('2026-03-20');

        $this->libro->registrar(
            suscripcion: $suscripcion,
            bolsa: BolsaDeHoras::Sala,
            cantidad: 6,
            motivo: MotivoMovimiento::AjusteManual,
            nota: 'Consumo de marzo.',
            en: $enMarzo,
        );

        $this->assertSame(6.0, $this->libro->consumoDelCiclo($suscripcion, BolsaDeHoras::Sala, $enMarzo));
        $this->assertSame(4.0, $this->libro->saldoDelCiclo($suscripcion, BolsaDeHoras::Sala, $enMarzo));

        // Al ciclo siguiente la bolsa vuelve a estar entera, sin borrar nada.
        $enAbril = CarbonImmutable::parse('2026-04-20');

        $this->assertSame(0.0, $this->libro->consumoDelCiclo($suscripcion, BolsaDeHoras::Sala, $enAbril));
        $this->assertSame(10.0, $this->libro->saldoDelCiclo($suscripcion, BolsaDeHoras::Sala, $enAbril));

        // Y el histórico sigue ahí: el libro no olvida.
        $this->assertSame(1, MovimientoHoras::where('suscripcion_id', $suscripcion->id)->count());
    }

    // ── Idempotencia del reinicio (Fase 1.5) ────────────────────────────────

    public function test_abrir_el_ciclo_dos_veces_no_lo_duplica(): void
    {
        $suscripcion = $this->nodoProDesde('2026-03-15');
        $en          = CarbonImmutable::parse('2026-03-15');

        $this->assertTrue($this->libro->abrirCiclo($suscripcion, $en));
        $this->assertFalse($this->libro->abrirCiclo($suscripcion, $en), 'La segunda pasada no debe abrir nada.');

        $marcadores = MovimientoHoras::where('suscripcion_id', $suscripcion->id)
            ->where('motivo', MotivoMovimiento::ReinicioCiclo->value)
            ->count();

        // Una marca por bolsa incluida en Nodo Pro: sala, contenido y días.
        $this->assertSame(count($suscripcion->plan->bolsasIncluidas()), $marcadores);
    }

    public function test_el_comando_de_ciclos_es_idempotente(): void
    {
        $suscripcion = $this->nodoProDesde(CarbonImmutable::today()->toDateString());

        Artisan::call('nodico:reiniciar-ciclos');
        Artisan::call('nodico:reiniciar-ciclos');
        Artisan::call('nodico:reiniciar-ciclos');

        $marcadores = MovimientoHoras::where('suscripcion_id', $suscripcion->id)
            ->where('motivo', MotivoMovimiento::ReinicioCiclo->value)
            ->count();

        $this->assertSame(
            count($suscripcion->plan->bolsasIncluidas()),
            $marcadores,
            'Tres pasadas del comando dejaron más de un marcador por bolsa.'
        );
    }

    /** Si el servidor estuvo caído, `--desde` recupera sin duplicar lo ya hecho. */
    public function test_el_comando_recupera_dias_perdidos_sin_duplicar(): void
    {
        $hace2Meses  = CarbonImmutable::today()->subMonths(2)->toDateString();
        $suscripcion = $this->nodoProDesde($hace2Meses);

        Artisan::call('nodico:reiniciar-ciclos', ['--desde' => $hace2Meses]);
        $primera = MovimientoHoras::where('motivo', MotivoMovimiento::ReinicioCiclo->value)->count();

        Artisan::call('nodico:reiniciar-ciclos', ['--desde' => $hace2Meses]);
        $segunda = MovimientoHoras::where('motivo', MotivoMovimiento::ReinicioCiclo->value)->count();

        $this->assertGreaterThan(0, $primera, 'La recuperación debió abrir los ciclos perdidos.');
        $this->assertSame($primera, $segunda, 'Repetir la recuperación duplicó ciclos.');
    }

    // ── Consistencia caché ↔ libro ──────────────────────────────────────────

    public function test_el_contador_en_cache_siempre_coincide_con_el_libro(): void
    {
        $suscripcion = $this->nodoProDesde(CarbonImmutable::today()->subDays(3)->toDateString());

        foreach ([2, 1.5, -0.5, 3] as $cantidad) {
            $this->libro->registrar(
                suscripcion: $suscripcion,
                bolsa: BolsaDeHoras::Sala,
                cantidad: $cantidad,
                motivo: MotivoMovimiento::AjusteManual,
                nota: 'Movimiento de prueba.',
            );
        }

        $suscripcion->refresh();

        $this->assertSame([], $this->libro->verificarConsistencia($suscripcion));
        $this->assertSame(6.0, (float) $suscripcion->horas_sala_usadas);
        $this->assertSame(6.0, $this->libro->consumoDelCiclo($suscripcion, BolsaDeHoras::Sala));
    }

    /** Y si alguien escribe el contador a mano, la divergencia se detecta y se repara. */
    public function test_una_escritura_directa_al_contador_se_detecta_y_se_repara(): void
    {
        $suscripcion = $this->nodoProDesde(CarbonImmutable::today()->subDays(3)->toDateString());

        $this->libro->registrar(
            suscripcion: $suscripcion,
            bolsa: BolsaDeHoras::Sala,
            cantidad: 3,
            motivo: MotivoMovimiento::AjusteManual,
            nota: 'Consumo legítimo.',
        );

        // Escritura a mano, saltándose el libro: justo lo que ya no se hace.
        $suscripcion->forceFill(['horas_sala_usadas' => 99])->save();

        $divergencias = $this->libro->verificarConsistencia($suscripcion->refresh());

        $this->assertArrayHasKey('sala', $divergencias);
        $this->assertSame(99.0, $divergencias['sala']['cache']);
        $this->assertSame(3.0, $divergencias['sala']['libro']);

        $this->libro->reconstruirCache($suscripcion);

        $this->assertSame(3.0, (float) $suscripcion->refresh()->horas_sala_usadas);
        $this->assertSame([], $this->libro->verificarConsistencia($suscripcion));
    }

    public function test_el_comando_de_saldos_falla_cuando_hay_divergencia(): void
    {
        $suscripcion = $this->nodoProDesde(CarbonImmutable::today()->subDays(3)->toDateString());
        $suscripcion->forceFill(['horas_sala_usadas' => 7])->save();

        $this->assertSame(1, Artisan::call('nodico:reconstruir-saldos'), 'Debe salir con error.');
        $this->assertSame(0, Artisan::call('nodico:reconstruir-saldos', ['--arreglar' => true]));
        $this->assertSame(0.0, (float) $suscripcion->refresh()->horas_sala_usadas);
    }

    // ── Auditoría ───────────────────────────────────────────────────────────

    public function test_un_ajuste_manual_sin_nota_no_se_puede_registrar(): void
    {
        $suscripcion = $this->nodoProDesde(CarbonImmutable::today()->toDateString());

        $this->expectException(\InvalidArgumentException::class);

        $this->libro->registrar(
            suscripcion: $suscripcion,
            bolsa: BolsaDeHoras::Sala,
            cantidad: 2,
            motivo: MotivoMovimiento::AjusteManual,
        );
    }

    /** Cada hora consumida tiene su porqué: eso es lo que un contador no daba. */
    public function test_cada_movimiento_guarda_motivo_autor_y_ciclo(): void
    {
        $plan = Plane::factory()->nodoPro()->create();
        $user = User::factory()->miembro()->create();

        $suscripcion = Suscripcion::factory()->delPlan($plan)->create([
            'user_id'      => $user->id,
            'fecha_inicio' => today()->subDays(3),
            'fecha_fin'    => today()->addMonth(),
        ]);

        $sala = Espacio::factory()->salaJuntas()->create();

        $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id'  => $sala->id,
            'fecha'       => today()->next(\Carbon\Carbon::MONDAY)->toDateString(),
            'hora_inicio' => '09:00',
            'hora_fin'    => '11:00',
        ])->assertSessionHasNoErrors();

        $movimiento = MovimientoHoras::first();

        $this->assertSame(2.0, $movimiento->cantidad);
        $this->assertSame(MotivoMovimiento::Reserva, $movimiento->motivo);
        $this->assertSame(BolsaDeHoras::Sala, $movimiento->bolsa);
        $this->assertSame($user->id, $movimiento->creado_por_user_id);
        $this->assertSame(Reserva::first()->id, $movimiento->reserva_id);
        $this->assertSame(
            $suscripcion->cicloInicio()->toDateString(),
            $movimiento->ciclo_inicio->toDateString(),
        );
    }

    /** Cancelar deja su propio apunte: el libro cuenta la historia entera. */
    public function test_cancelar_deja_rastro_de_la_devolucion(): void
    {
        $plan = Plane::factory()->nodoPro()->create();
        $user = User::factory()->miembro()->create();

        Suscripcion::factory()->delPlan($plan)->create([
            'user_id'      => $user->id,
            'fecha_inicio' => today()->subDays(3),
            'fecha_fin'    => today()->addMonth(),
        ]);

        $sala = Espacio::factory()->salaJuntas()->create();

        $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id'  => $sala->id,
            'fecha'       => today()->next(\Carbon\Carbon::MONDAY)->toDateString(),
            'hora_inicio' => '09:00',
            'hora_fin'    => '11:00',
        ]);

        $this->actingAs($user)->delete(route('portal.reservas.cancel', Reserva::first()));

        $movimientos = MovimientoHoras::orderBy('id')->get();

        $this->assertCount(2, $movimientos);
        $this->assertSame(2.0, $movimientos[0]->cantidad);
        $this->assertSame(-2.0, $movimientos[1]->cantidad);
        $this->assertSame(MotivoMovimiento::Cancelacion, $movimientos[1]->motivo);
        $this->assertTrue($movimientos[1]->devuelve());
    }

    // ── No-show (Fase 1.6) ──────────────────────────────────────────────────

    public function test_el_no_show_marca_la_reserva_y_no_devuelve_las_horas(): void
    {
        $plan = Plane::factory()->nodoPro()->create();
        $user = User::factory()->miembro()->create();

        $suscripcion = Suscripcion::factory()->delPlan($plan)->create([
            'user_id'      => $user->id,
            'fecha_inicio' => today()->subDays(10),
            'fecha_fin'    => today()->addMonth(),
        ]);

        $sala = Espacio::factory()->salaJuntas()->create();

        // Una reserva de ayer a la que nadie se presentó.
        $reserva = Reserva::factory()->create([
            'user_id'        => $user->id,
            'espacio_id'     => $sala->id,
            'suscripcion_id' => $suscripcion->id,
            'fecha'          => today()->subDay(),
            'hora_inicio'    => '09:00:00',
            'hora_fin'       => '11:00:00',
        ]);

        $this->libro->registrar(
            suscripcion: $suscripcion,
            bolsa: BolsaDeHoras::Sala,
            cantidad: 2,
            motivo: MotivoMovimiento::Reserva,
            reserva: $reserva,
        );

        Artisan::call('nodico:marcar-no-show');

        $this->assertSame('No_Show', $reserva->refresh()->estatus);
        $this->assertSame(
            2.0,
            (float) $suscripcion->refresh()->horas_sala_usadas,
            'El no-show NO devuelve horas: es lo que hace que la regla de las 2 h signifique algo.'
        );

        $marca = MovimientoHoras::where('motivo', MotivoMovimiento::NoShow->value)->first();
        $this->assertNotNull($marca);
        $this->assertSame(0.0, $marca->cantidad);
    }

    public function test_el_no_show_no_toca_una_reserva_con_checkin(): void
    {
        $plan = Plane::factory()->nodoPro()->create();
        $user = User::factory()->miembro()->create();

        $suscripcion = Suscripcion::factory()->delPlan($plan)->create([
            'user_id'   => $user->id,
            'fecha_fin' => today()->addMonth(),
        ]);

        $sala = Espacio::factory()->salaJuntas()->create();

        $reserva = Reserva::factory()->create([
            'user_id'        => $user->id,
            'espacio_id'     => $sala->id,
            'suscripcion_id' => $suscripcion->id,
            'fecha'          => today()->subDay(),
            'hora_inicio'    => '09:00:00',
            'hora_fin'       => '11:00:00',
        ]);

        $reserva->checkin()->create([
            'user_id'      => $user->id,
            'espacio_id'   => $sala->id,
            'hora_entrada' => today()->subDay()->setTime(9, 5),
        ]);

        Artisan::call('nodico:marcar-no-show');

        $this->assertSame('Confirmada', $reserva->refresh()->estatus);
    }

    public function test_el_no_show_respeta_el_margen_de_cortesia(): void
    {
        $plan = Plane::factory()->nodoPro()->create();
        $user = User::factory()->miembro()->create();

        $suscripcion = Suscripcion::factory()->delPlan($plan)->create([
            'user_id'   => $user->id,
            'fecha_fin' => today()->addMonth(),
        ]);

        $sala  = Espacio::factory()->salaJuntas()->create();
        $ahora = CarbonImmutable::now();

        // Una reserva que acaba de terminar: dentro del margen, no se marca.
        $reserva = Reserva::factory()->create([
            'user_id'        => $user->id,
            'espacio_id'     => $sala->id,
            'suscripcion_id' => $suscripcion->id,
            'fecha'          => today(),
            'hora_inicio'    => $ahora->subHours(1)->format('H:i:s'),
            'hora_fin'       => $ahora->subMinutes(5)->format('H:i:s'),
        ]);

        Artisan::call('nodico:marcar-no-show', ['--margen' => 15]);

        $this->assertSame(
            'Confirmada',
            $reserva->refresh()->estatus,
            'Con 5 minutos de retraso y 15 de cortesía, todavía no es un no-show.'
        );
    }
}
