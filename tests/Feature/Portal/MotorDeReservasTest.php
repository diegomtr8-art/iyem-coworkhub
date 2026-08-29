<?php

namespace Tests\Feature\Portal;

use App\Enums\BolsaDeHoras;
use App\Enums\MotivoMovimiento;
use App\Enums\TipoEspacio;
use App\Models\Checkin;
use App\Models\Espacio;
use App\Models\Plane;
use App\Models\Reserva;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Horas\LibroDeHoras;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 0 — el motor de reservas.
 *
 * Cubre los siete defectos del prompt de portales. La de
 * `test_una_reserva_de_dos_horas_consume_exactamente_dos_horas` es la que
 * importa: **falla contra el código anterior** y es la razón de que el resto
 * del control de cupos no sirviera para nada.
 */
class MotorDeReservasTest extends TestCase
{
    use RefreshDatabase;

    /** Próximo lunes: toda reserva de prueba cae en día hábil y dentro de horario. */
    private function diaHabil(int $sumarDias = 0): string
    {
        return today()->next(Carbon::MONDAY)->addDays($sumarDias)->toDateString();
    }

    /** Gasta parte de una bolsa a través del libro, que es lo que manda. */
    private function consumir(Suscripcion $suscripcion, BolsaDeHoras $bolsa, float $cantidad): void
    {
        app(LibroDeHoras::class)->registrar(
            suscripcion: $suscripcion,
            bolsa: $bolsa,
            cantidad: $cantidad,
            motivo: MotivoMovimiento::AjusteManual,
            nota: 'Consumo previo, montado por la prueba.',
        );

        $suscripcion->refresh();
    }

    /** @return array{0: User, 1: Suscripcion, 2: Espacio} */
    private function miembroConNodoPro(): array
    {
        $plan = Plane::factory()->nodoPro()->create();
        $user = User::factory()->miembro()->create();

        $suscripcion = Suscripcion::factory()->delPlan($plan)->create([
            'user_id'      => $user->id,
            'fecha_inicio' => today()->subDays(5),
            'fecha_fin'    => today()->addMonth(),
        ]);

        $sala = Espacio::factory()->salaJuntas()->create();

        return [$user, $suscripcion, $sala];
    }

    // ── BUG-01 · la contabilidad de horas estaba invertida ───────────────────

    /**
     * LA prueba de regresión. En Carbon 3 `$fin->diffInMinutes($inicio)` mide
     * de fin a inicio y devuelve **-120**, no 120. El código anterior sumaba
     * ese negativo a `horas_sala_usadas`, así que reservar **regalaba** horas.
     *
     * Si alguien vuelve a invertir los operandos, esto se pone en -2 y falla.
     */
    public function test_una_reserva_de_dos_horas_consume_exactamente_dos_horas(): void
    {
        [$user, $suscripcion, $sala] = $this->miembroConNodoPro();

        $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id'  => $sala->id,
            'fecha'       => $this->diaHabil(),
            'hora_inicio' => '09:00',
            'hora_fin'    => '11:00',
        ])->assertSessionHasNoErrors();

        $suscripcion->refresh();

        $this->assertSame(
            2.0,
            (float) $suscripcion->horas_sala_usadas,
            'Una reserva de 2 h debe consumir 2 h. En negativo, reservar regala horas (BUG-01).'
        );
        $this->assertSame(8.0, $suscripcion->horasSalaRestantes());
    }

    /**
     * La consecuencia directa del signo invertido: `restantes < $horas` con
     * `$horas` negativo es falso siempre, así que **el cupo no existía**.
     */
    public function test_no_se_puede_reservar_sin_bolsa_suficiente(): void
    {
        [$user, $suscripcion, $sala] = $this->miembroConNodoPro();

        // 10 de 10 horas gastadas. Se consume **por el libro**, que desde la
        // Fase 1.1 es la fuente de verdad: escribir el contador a mano ya no
        // significa nada, y eso es justo lo que se quiere.
        $this->consumir($suscripcion, BolsaDeHoras::Sala, 10);

        $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id'  => $sala->id,
            'fecha'       => $this->diaHabil(),
            'hora_inicio' => '09:00',
            'hora_fin'    => '11:00',
        ])->assertSessionHasErrors();

        $this->assertSame(0, Reserva::count(), 'Sin bolsa no puede quedar una reserva escrita.');
        $this->assertSame(10.0, (float) $suscripcion->refresh()->horas_sala_usadas);
    }

    /** El tope diario acumulaba negativos, así que nunca se alcanzaba. */
    public function test_el_tope_de_dos_horas_por_dia_suma_varias_reservas_del_mismo_dia(): void
    {
        [$user, $suscripcion, $sala] = $this->miembroConNodoPro();
        $otraSala = Espacio::factory()->salaJuntas()->create(['nombre' => 'Sala 2']);
        $fecha    = $this->diaHabil();

        // Primera hora del día: pasa.
        $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id' => $sala->id, 'fecha' => $fecha,
            'hora_inicio' => '09:00', 'hora_fin' => '10:00',
        ])->assertSessionHasNoErrors();

        // Segunda hora, en otra sala para que no choque el traslape: llega al tope.
        $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id' => $otraSala->id, 'fecha' => $fecha,
            'hora_inicio' => '11:00', 'hora_fin' => '12:00',
        ])->assertSessionHasNoErrors();

        // Tercera: serían 3 h en el día, por encima de las 2 de Nodo Pro.
        $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id' => $sala->id, 'fecha' => $fecha,
            'hora_inicio' => '13:00', 'hora_fin' => '14:00',
        ])->assertSessionHasErrors();

        $this->assertSame(2, Reserva::where('estatus', 'Confirmada')->count());
        $this->assertSame(2.0, (float) $suscripcion->refresh()->horas_sala_usadas);
    }

    // ── BUG-03 · carrera en la doble reserva ─────────────────────────────────

    /** Dos reservas sobre la misma sala y horario: solo una puede quedar. */
    public function test_dos_reservas_sobre_la_misma_sala_y_horario_solo_deja_una(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();

        $otroPlan = Plane::factory()->nodoPro()->create();
        $otro     = User::factory()->miembro()->create();
        Suscripcion::factory()->delPlan($otroPlan)->create([
            'user_id'   => $otro->id,
            'fecha_fin' => today()->addMonth(),
        ]);

        $fecha  = $this->diaHabil();
        $cuerpo = ['espacio_id' => $sala->id, 'fecha' => $fecha, 'hora_inicio' => '09:00', 'hora_fin' => '10:00'];

        $this->actingAs($user)->post(route('portal.reservar.store'), $cuerpo)->assertSessionHasNoErrors();
        $this->actingAs($otro)->post(route('portal.reservar.store'), $cuerpo)->assertSessionHasErrors();

        $this->assertSame(1, Reserva::where('estatus', 'Confirmada')->count());
    }

    /** Un traslape parcial también choca: 09:30–10:30 pisa a 09:00–10:00. */
    public function test_un_traslape_parcial_tambien_se_rechaza(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();
        $fecha = $this->diaHabil();

        $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id' => $sala->id, 'fecha' => $fecha,
            'hora_inicio' => '09:00', 'hora_fin' => '10:00',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id' => $sala->id, 'fecha' => $fecha,
            'hora_inicio' => '09:30', 'hora_fin' => '10:30',
        ])->assertSessionHasErrors();

        $this->assertSame(1, Reserva::where('estatus', 'Confirmada')->count());
    }

    /**
     * La comprobación en aplicación no basta: la base tiene que rechazar el
     * traslape aunque alguien escriba saltándose el controlador. Es lo que
     * cierra de verdad la carrera del BUG-03.
     */
    public function test_la_base_de_datos_rechaza_un_traslape_escrito_por_fuera(): void
    {
        [$user, $suscripcion, $sala] = $this->miembroConNodoPro();
        $fecha = $this->diaHabil();

        $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id' => $sala->id, 'fecha' => $fecha,
            'hora_inicio' => '09:00', 'hora_fin' => '10:00',
        ])->assertSessionHasNoErrors();

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Escritura directa, sin pasar por el controlador ni por sus guardas.
        app(\App\Servicios\Reservas\RegistroDeBloques::class)->ocupar(
            Reserva::factory()->create([
                'user_id'        => $user->id,
                'espacio_id'     => $sala->id,
                'suscripcion_id' => $suscripcion->id,
                'fecha'          => $fecha,
                'hora_inicio'    => '09:30:00',
                'hora_fin'       => '10:30:00',
            ])
        );
    }

    // ── BUG-04 · doble cancelación ───────────────────────────────────────────

    public function test_cancelar_devuelve_las_horas(): void
    {
        [$user, $suscripcion, $sala] = $this->miembroConNodoPro();

        $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id' => $sala->id, 'fecha' => $this->diaHabil(),
            'hora_inicio' => '09:00', 'hora_fin' => '11:00',
        ]);

        $this->assertSame(2.0, (float) $suscripcion->refresh()->horas_sala_usadas);

        $reserva = Reserva::first();
        $this->actingAs($user)->delete(route('portal.reservas.cancel', $reserva));

        $this->assertSame(0.0, (float) $suscripcion->refresh()->horas_sala_usadas);
        $this->assertSame('Cancelada', $reserva->refresh()->estatus);
    }

    /** Dos peticiones seguidas devolvían las horas dos veces. */
    public function test_cancelar_dos_veces_no_devuelve_las_horas_dos_veces(): void
    {
        [$user, $suscripcion, $sala] = $this->miembroConNodoPro();

        $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id' => $sala->id, 'fecha' => $this->diaHabil(),
            'hora_inicio' => '09:00', 'hora_fin' => '11:00',
        ]);

        $reserva = Reserva::first();

        $this->actingAs($user)->delete(route('portal.reservas.cancel', $reserva));
        $this->actingAs($user)->delete(route('portal.reservas.cancel', $reserva));

        $this->assertSame(
            0.0,
            (float) $suscripcion->refresh()->horas_sala_usadas,
            'La segunda cancelación no puede devolver nada: el saldo se iría por encima del cupo.'
        );
    }

    /** Cancelada la reserva, su horario vuelve a estar libre para cualquiera. */
    public function test_al_cancelar_el_horario_queda_libre_otra_vez(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();
        $fecha  = $this->diaHabil();
        $cuerpo = ['espacio_id' => $sala->id, 'fecha' => $fecha, 'hora_inicio' => '09:00', 'hora_fin' => '10:00'];

        $this->actingAs($user)->post(route('portal.reservar.store'), $cuerpo);
        $this->actingAs($user)->delete(route('portal.reservas.cancel', Reserva::first()));

        $this->actingAs($user)->post(route('portal.reservar.store'), $cuerpo)->assertSessionHasNoErrors();

        $this->assertSame(1, Reserva::where('estatus', 'Confirmada')->count());
    }

    /** Un miembro no puede cancelar la reserva de otro. */
    public function test_un_miembro_no_puede_cancelar_la_reserva_de_otro(): void
    {
        [$user, , $sala] = $this->miembroConNodoPro();
        $intruso = User::factory()->miembro()->create();

        $reserva = Reserva::factory()->create([
            'user_id'    => $user->id,
            'espacio_id' => $sala->id,
            'fecha'      => $this->diaHabil(),
        ]);

        $this->actingAs($intruso)->delete(route('portal.reservas.cancel', $reserva))->assertForbidden();
        $this->assertSame('Confirmada', $reserva->refresh()->estatus);
    }

    // ── BUG-05 · dias_usados nunca se incrementaba ───────────────────────────

    public function test_el_checkin_consume_un_dia_una_sola_vez_por_dia_natural(): void
    {
        $plan = Plane::factory()->flex()->create();
        $user = User::factory()->miembro()->create();

        $suscripcion = Suscripcion::factory()->delPlan($plan)->create([
            'user_id'   => $user->id,
            'fecha_fin' => today()->addDays(30),
        ]);

        Espacio::factory()->coworking()->create();

        $this->actingAs($user)->post(route('portal.checkin.entrada'));
        $this->assertSame(1, $suscripcion->refresh()->dias_usados);

        // Salir y volver a entrar el mismo día no gasta un segundo día.
        $this->actingAs($user)->post(route('portal.checkin.salida'));
        $this->actingAs($user)->post(route('portal.checkin.entrada'));

        $this->assertSame(
            1,
            $suscripcion->refresh()->dias_usados,
            'Dos entradas el mismo día natural son un solo día consumido.'
        );
        $this->assertSame(3, $suscripcion->diasRestantesMes());
    }

    public function test_sin_dias_restantes_no_se_puede_hacer_checkin(): void
    {
        $plan = Plane::factory()->flex()->create();
        $user = User::factory()->miembro()->create();

        $suscripcion = Suscripcion::factory()->delPlan($plan)->create([
            'user_id'   => $user->id,
            'fecha_fin' => today()->addDays(30),
        ]);

        // Los 4 días de Flex, gastados en días anteriores.
        $this->consumir($suscripcion, BolsaDeHoras::Dias, 4);

        Espacio::factory()->coworking()->create();

        $this->actingAs($user)->post(route('portal.checkin.entrada'))->assertSessionHasErrors();

        $this->assertSame(0, Checkin::count());
        $this->assertSame(4, (int) $suscripcion->refresh()->dias_usados);
    }

    /** Nodo Pro es ilimitado: el check-in no consume días ni tiene tope. */
    public function test_un_plan_ilimitado_no_consume_dias(): void
    {
        [$user, $suscripcion] = $this->miembroConNodoPro();
        Espacio::factory()->coworking()->create();

        $this->actingAs($user)->post(route('portal.checkin.entrada'))->assertSessionHasNoErrors();

        $this->assertSame(0, $suscripcion->refresh()->dias_usados);
        $this->assertNull($suscripcion->diasRestantesMes());
    }

    // ── BUG-06 · etiquetas de tipo de espacio ────────────────────────────────

    public function test_todo_tipo_de_espacio_tiene_etiqueta_legible(): void
    {
        foreach (TipoEspacio::cases() as $tipo) {
            $espacio = Espacio::factory()->create(['tipo' => $tipo->value]);

            $this->assertNotSame(
                $tipo->value,
                $espacio->tipo_label,
                "El tipo «{$tipo->value}» se está mostrando en crudo al usuario."
            );
        }
    }

    // ── BUG-02 · una sola fuente de verdad para el permiso ───────────────────

    /** La bolsa manda: sin `horas_sala_mes` no hay salas, se mire por donde se mire. */
    public function test_un_plan_sin_bolsa_de_sala_no_puede_reservar_salas(): void
    {
        $plan = Plane::factory()->dayPass()->create();
        $user = User::factory()->miembro()->create();

        Suscripcion::factory()->delPlan($plan)->create([
            'user_id'   => $user->id,
            'fecha_fin' => today()->addDays(30),
        ]);

        $sala = Espacio::factory()->salaJuntas()->create();

        $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id' => $sala->id, 'fecha' => $this->diaHabil(),
            'hora_inicio' => '09:00', 'hora_fin' => '10:00',
        ])->assertSessionHasErrors();

        $this->assertSame(0, Reserva::count());
    }

    /**
     * `incluye_sala_juntas` y `horas_sala_mes` eran dos campos gobernando la
     * misma regla y podían contradecirse. Ahora el primero se deriva del
     * segundo y no hay forma de que discrepen.
     */
    public function test_el_permiso_de_sala_se_deriva_de_la_bolsa(): void
    {
        $conBolsa = Plane::factory()->nodoPro()->create();
        $sinBolsa = Plane::factory()->dayPass()->create();

        $this->assertTrue($conBolsa->incluyeSalaJuntas());
        $this->assertFalse($sinBolsa->incluyeSalaJuntas());
        $this->assertTrue($conBolsa->tieneAccesoSala());
        $this->assertFalse($sinBolsa->tieneAccesoSala());
    }

    /** Fuera de la vigencia de la membresía no se reserva. */
    public function test_no_se_puede_reservar_fuera_de_la_vigencia_de_la_membresia(): void
    {
        $plan = Plane::factory()->nodoPro()->create();
        $user = User::factory()->miembro()->create();

        Suscripcion::factory()->delPlan($plan)->create([
            'user_id'      => $user->id,
            'fecha_inicio' => today()->subDays(10),
            'fecha_fin'    => today()->addDays(2),
        ]);

        $sala = Espacio::factory()->salaJuntas()->create();

        $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id'  => $sala->id,
            'fecha'       => today()->addDays(20)->toDateString(),
            'hora_inicio' => '09:00',
            'hora_fin'    => '10:00',
        ])->assertSessionHasErrors();

        $this->assertSame(0, Reserva::count());
    }
}
