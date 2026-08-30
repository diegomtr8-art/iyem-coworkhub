<?php

namespace Tests\Feature\Portal;

use App\Enums\BolsaDeHoras;
use App\Enums\MotivoMovimiento;
use App\Models\DatosFiscales;
use App\Models\Espacio;
use App\Models\Plane;
use App\Models\Reserva;
use App\Models\SolicitudAsesoria;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Horas\LibroDeHoras;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Fase 2 — las ocho pantallas del portal del miembro.
 *
 * Se comprueba que responden, que llevan los datos que la pantalla promete y,
 * sobre todo, **que un miembro no ve ni toca lo de otro**.
 */
class PantallasDelPortalTest extends TestCase
{
    use RefreshDatabase;

    private function miembroConPlan(string $estado = 'nodoPro'): array
    {
        $plan = Plane::factory()->{$estado}()->create($estado === 'nodoPro' ? [
            'horas_asesoria_mes'     => 4,
            'max_horas_asesoria_dia' => 1,
        ] : []);

        $user = User::factory()->miembro()->create();

        $suscripcion = Suscripcion::factory()->delPlan($plan)->create([
            'user_id'      => $user->id,
            'fecha_inicio' => today()->subDays(5),
            'fecha_fin'    => today()->addMonths(3),
        ]);

        return [$user, $suscripcion, $plan];
    }

    // ── Las ocho pantallas responden ────────────────────────────────────────

    public static function pantallas(): array
    {
        return [
            'inicio'          => ['portal.dashboard',      'Portal/Dashboard'],
            'reservar'        => ['portal.reservar',       'Portal/Reservar'],
            'mis reservas'    => ['portal.reservas',       'Portal/MisReservas'],
            'mi membresía'    => ['portal.suscripcion',    'Portal/MiMembresia'],
            'mi perfil'       => ['portal.perfil',         'Portal/Perfil'],
            'datos fiscales'  => ['portal.datos-fiscales', 'Portal/DatosFiscales'],
            'asesoría'        => ['portal.asesoria',       'Portal/Asesoria'],
            'accesos y pagos' => ['portal.accesos',        'Portal/Accesos'],
        ];
    }

    /** @dataProvider pantallas */
    public function test_la_pantalla_responde_y_renderiza_su_componente(string $ruta, string $componente): void
    {
        [$user] = $this->miembroConPlan();

        $this->actingAs($user)
            ->get(route($ruta))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $pagina) => $pagina->component($componente));
    }

    // ── 2.1 · Inicio ────────────────────────────────────────────────────────

    /**
     * La regla de oro: la pantalla tiene que poder responder «¿cuánto me queda
     * y hasta cuándo?» sin que nadie eche cuentas.
     */
    public function test_el_inicio_lleva_los_medidores_con_saldo_y_fecha_de_reinicio(): void
    {
        [$user, $suscripcion] = $this->miembroConPlan();

        app(LibroDeHoras::class)->registrar(
            suscripcion: $suscripcion,
            bolsa: BolsaDeHoras::Sala,
            cantidad: 4,
            motivo: MotivoMovimiento::AjusteManual,
            nota: 'Consumo previo.',
        );

        $this->actingAs($user)->get(route('portal.dashboard'))->assertInertia(
            fn (AssertableInertia $p) => $p
                ->has('medidores')
                ->where('medidores.0.bolsa', 'sala')
                ->where('medidores.0.incluida', true)
                // `where` compara con ===, y al serializar a JSON un 4.0 llega
                // como entero. Lo que importa aquí es el valor.
                ->where('medidores.0.usado', 4)
                ->where('medidores.0.restante', 6)
                ->where('medidores.0.porcentaje_usado', 40)
                ->whereNot('medidores.0.reinicia_texto', null)
                ->where('estadoMembresia.tiene', true)
        );
    }

    /**
     * Una bolsa que el plan no incluye **no se pinta en cero**: un cero se lee
     * como un error, y una invitación se lee como una oferta.
     */
    public function test_una_bolsa_no_incluida_llega_como_invitacion_y_no_como_cero(): void
    {
        // Day-Pass no tiene salas ni asesoría.
        [$user] = $this->miembroConPlan('dayPass');
        Plane::factory()->nodoPro()->create(['horas_asesoria_mes' => 4]);

        $this->actingAs($user)->get(route('portal.dashboard'))->assertInertia(function (AssertableInertia $p) {
            $medidores = collect($p->toArray()['props']['medidores']);
            $sala = $medidores->firstWhere('bolsa', 'sala');

            $this->assertFalse($sala['incluida']);
            $this->assertNull($sala['restante'], 'Sin acceso no es «cero horas», es «no aplica».');
            $this->assertNotNull($sala['sugerencia'], 'Debe decir qué plan la incluye.');
            $this->assertSame('Nodo Pro', $sala['sugerencia']['plan']);
        });
    }

    public function test_el_inicio_avisa_cuando_la_membresia_esta_por_vencer(): void
    {
        [$user, $suscripcion] = $this->miembroConPlan();
        $suscripcion->update(['fecha_fin' => today()->addDays(3)]);

        $this->actingAs($user)->get(route('portal.dashboard'))->assertInertia(
            fn (AssertableInertia $p) => $p
                ->where('estadoMembresia.tono', 'atencion')
                ->whereNot('estadoMembresia.accion', null)
        );
    }

    public function test_sin_membresia_el_inicio_lo_dice_y_ofrece_la_salida(): void
    {
        $user = User::factory()->miembro()->create();

        $this->actingAs($user)->get(route('portal.dashboard'))->assertInertia(
            fn (AssertableInertia $p) => $p
                ->where('estadoMembresia.tiene', false)
                ->where('estadoMembresia.tono', 'problema')
                ->where('estadoMembresia.accion', 'Ver los planes')
                ->where('medidores', [])
        );
    }

    // ── 2.5 · Reservar ──────────────────────────────────────────────────────

    public function test_la_disponibilidad_marca_los_bloques_ocupados(): void
    {
        [$user] = $this->miembroConPlan();
        $sala   = Espacio::factory()->salaJuntas()->create();
        $lunes  = today()->next(Carbon::MONDAY)->toDateString();

        $this->actingAs($user)->post(route('portal.reservar.store'), [
            'espacio_id' => $sala->id, 'fecha' => $lunes,
            'hora_inicio' => '10:00', 'hora_fin' => '11:00',
        ])->assertSessionHasNoErrors();

        $respuesta = $this->actingAs($user)->getJson(route('portal.disponibilidad', [
            'espacio_id' => $sala->id, 'fecha' => $lunes,
        ]));

        $respuesta->assertOk();
        $datos = $respuesta->json();

        $this->assertTrue($datos['abierto']);
        $this->assertSame('09:00', $datos['apertura']);
        $this->assertSame('19:00', $datos['cierre']);

        $ocupados = collect($datos['bloques'])->where('libre', false)->pluck('hora');
        $this->assertContains('10:00', $ocupados);
        $this->assertContains('10:30', $ocupados);
        $this->assertNotContains('11:00', $ocupados, 'El fin es exclusivo: las 11:00 quedan libres.');

        // Y los datos que hacen posible la validación en vivo.
        $this->assertEqualsWithDelta(1, $datos['usado_ese_dia'], 0.001);
        $this->assertEqualsWithDelta(9, $datos['saldo_ciclo'], 0.001);
        $this->assertEqualsWithDelta(2, $datos['tope_diario'], 0.001);
    }

    public function test_la_disponibilidad_dice_por_que_esta_cerrado(): void
    {
        [$user] = $this->miembroConPlan();
        $sala    = Espacio::factory()->salaJuntas()->create();
        $domingo = today()->next(Carbon::SUNDAY)->toDateString();

        $datos = $this->actingAs($user)->getJson(route('portal.disponibilidad', [
            'espacio_id' => $sala->id, 'fecha' => $domingo,
        ]))->json();

        $this->assertFalse($datos['abierto']);
        $this->assertNotNull($datos['motivo_cierre']);
        $this->assertSame([], $datos['bloques']);
    }

    /** El coworking no se reserva: no debe aparecer en el selector. */
    public function test_el_area_de_coworking_no_aparece_entre_los_espacios_reservables(): void
    {
        [$user] = $this->miembroConPlan();
        Espacio::factory()->coworking()->create();
        Espacio::factory()->salaJuntas()->create();

        $this->actingAs($user)->get(route('portal.reservar'))->assertInertia(function (AssertableInertia $p) {
            $tipos = collect($p->toArray()['props']['espacios'])->pluck('tipo');

            $this->assertNotContains('coworking', $tipos);
            $this->assertContains('sala_juntas', $tipos);
        });
    }

    public function test_un_miembro_no_puede_consultar_la_disponibilidad_de_un_espacio_que_su_plan_no_incluye(): void
    {
        [$user] = $this->miembroConPlan('dayPass');
        $sala = Espacio::factory()->salaJuntas()->create();

        $this->actingAs($user)
            ->getJson(route('portal.disponibilidad', ['espacio_id' => $sala->id, 'fecha' => today()->toDateString()]))
            ->assertForbidden();
    }

    // ── 2.6 · Mis reservas ──────────────────────────────────────────────────

    /** El botón de cancelar debe poder decir si devuelve horas ANTES de pulsarlo. */
    public function test_mis_reservas_dice_por_adelantado_si_cancelar_devuelve_las_horas(): void
    {
        [$user, $suscripcion] = $this->miembroConPlan();
        $sala = Espacio::factory()->salaJuntas()->create();

        // Lejana: devuelve.
        Reserva::factory()->create([
            'user_id' => $user->id, 'espacio_id' => $sala->id, 'suscripcion_id' => $suscripcion->id,
            'fecha' => today()->addDays(5), 'hora_inicio' => '10:00:00', 'hora_fin' => '11:00:00',
        ]);

        // Dentro de una hora: ya no.
        Reserva::factory()->create([
            'user_id' => $user->id, 'espacio_id' => $sala->id, 'suscripcion_id' => $suscripcion->id,
            'fecha' => today(),
            'hora_inicio' => now()->addHour()->format('H:i:s'),
            'hora_fin' => now()->addHours(2)->format('H:i:s'),
        ]);

        $this->actingAs($user)->get(route('portal.reservas'))->assertInertia(function (AssertableInertia $p) {
            $proximas = collect($p->toArray()['props']['proximas']);

            $this->assertCount(2, $proximas);

            // Se distinguen por la hora de inicio: la de las 10:00 es la lejana.
            $lejana  = $proximas->firstWhere('hora_inicio', '10:00:00');
            $cercana = $proximas->first(fn ($r) => $r['hora_inicio'] !== '10:00:00');

            $this->assertTrue($lejana['cancelar_devuelve'], 'A cinco días vista, cancelar devuelve.');
            $this->assertFalse($cercana['cancelar_devuelve'], 'A una hora vista, ya no.');

            // Todas traen el dato y el límite, que es lo que la vista necesita
            // para poder avisar ANTES de que se pulse el botón.
            foreach ($proximas as $reserva) {
                $this->assertArrayHasKey('cancelar_devuelve', $reserva);
                $this->assertArrayHasKey('limite_cancelacion', $reserva);
                $this->assertArrayHasKey('horas', $reserva);
            }
        });
    }

    // ── 2.3 · Datos fiscales ────────────────────────────────────────────────

    public function test_los_datos_fiscales_se_guardan_normalizados_desde_el_portal(): void
    {
        [$user] = $this->miembroConPlan();

        $this->actingAs($user)->put(route('portal.datos-fiscales.update'), [
            'rfc'               => 'xaxx-010101-000',
            'razon_social'      => 'Público en general',
            'regimen_fiscal'    => '612',
            'uso_cfdi'          => 'G03',
            'codigo_postal'     => '97110',
            'email_facturacion' => 'facturas@ejemplo.mx',
        ])->assertSessionHasNoErrors();

        $this->assertSame('XAXX010101000', $user->refresh()->datosFiscales->rfc);
    }

    public function test_un_regimen_de_persona_fisica_no_vale_con_un_rfc_de_persona_moral(): void
    {
        [$user] = $this->miembroConPlan();

        $this->actingAs($user)->put(route('portal.datos-fiscales.update'), [
            'rfc'               => 'ABC010101AB1',  // 12 posiciones: persona moral
            'razon_social'      => 'Empresa SA de CV',
            'regimen_fiscal'    => '612',           // solo personas físicas
            'uso_cfdi'          => 'G03',
            'codigo_postal'     => '97110',
            'email_facturacion' => 'facturas@empresa.mx',
        ])->assertSessionHasErrors('regimen_fiscal');
    }

    public function test_la_pantalla_explica_que_la_factura_la_emite_contabilidad(): void
    {
        [$user] = $this->miembroConPlan();

        $this->actingAs($user)->get(route('portal.datos-fiscales'))->assertInertia(
            fn (AssertableInertia $p) => $p
                ->has('proceso.emisor')
                ->has('proceso.dias_habiles')
                ->has('proceso.contacto')
                ->has('regimenes')
                ->has('usosCfdi')
        );
    }

    // ── 2.7 · Asesoría ──────────────────────────────────────────────────────

    public function test_un_plan_sin_asesoria_ve_la_invitacion_y_no_el_formulario(): void
    {
        [$user] = $this->miembroConPlan('flex');
        Plane::factory()->nodoPro()->create(['horas_asesoria_mes' => 4]);

        $this->actingAs($user)->get(route('portal.asesoria'))->assertInertia(
            fn (AssertableInertia $p) => $p
                ->where('incluida', false)
                ->where('bolsa', null)
                ->whereNot('planQueLaIncluye', null)
        );
    }

    public function test_solicitar_asesoria_desde_el_portal_no_descuenta_horas(): void
    {
        [$user, $suscripcion] = $this->miembroConPlan();

        // Fase 4.D — el tema se elige del catálogo (`tema_id`), no como texto libre.
        $tema = \App\Models\TemaAsesoria::create([
            'nombre' => 'Precios y costos', 'categoria' => 'basicos', 'duracion_min' => 60,
        ]);

        $this->actingAs($user)->post(route('portal.asesoria.store'), [
            'tema_id'           => $tema->id,
            'detalle'           => 'Quiero revisar mis precios y mi punto de equilibrio.',
            'dia_preferido'     => today()->addDays(4)->toDateString(),
            'horario_preferido' => 'Por la mañana (9:00 a 12:00)',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, SolicitudAsesoria::count());
        $this->assertSame(0.0, (float) $suscripcion->refresh()->horas_asesoria_usadas);
    }

    // ── Aislamiento entre miembros ──────────────────────────────────────────

    /** Lo que ningún miembro puede hacer: ver o tocar lo de otro. */
    public function test_un_miembro_no_ve_ni_toca_nada_de_otro_miembro(): void
    {
        [$duenio, $suscripcion] = $this->miembroConPlan();
        [$intruso]              = $this->miembroConPlan();

        $sala = Espacio::factory()->salaJuntas()->create();

        $reserva = Reserva::factory()->create([
            'user_id' => $duenio->id, 'espacio_id' => $sala->id, 'suscripcion_id' => $suscripcion->id,
            'fecha' => today()->addDays(3),
        ]);

        $asesoria = SolicitudAsesoria::create([
            'user_id' => $duenio->id, 'suscripcion_id' => $suscripcion->id,
            'tema' => 'Privado', 'dia_preferido' => today()->addDays(3),
            'horario_preferido' => 'Mañana', 'horas' => 1,
        ]);

        DatosFiscales::create([
            'user_id' => $duenio->id, 'rfc' => 'XAXX010101000',
            'razon_social' => 'Público en general', 'regimen_fiscal' => '612',
            'uso_cfdi' => 'G03', 'codigo_postal' => '97110',
            'email_facturacion' => 'privado@ejemplo.mx',
        ]);

        // No puede cancelar su reserva ni su asesoría.
        $this->actingAs($intruso)->delete(route('portal.reservas.cancel', $reserva))->assertForbidden();
        $this->actingAs($intruso)->delete(route('portal.asesoria.cancel', $asesoria))->assertForbidden();

        $this->assertSame('Confirmada', $reserva->refresh()->estatus);

        // Y sus pantallas solo enseñan lo suyo.
        $this->actingAs($intruso)->get(route('portal.reservas'))->assertInertia(
            fn (AssertableInertia $p) => $p->where('proximas', [])
        );

        $this->actingAs($intruso)->get(route('portal.asesoria'))->assertInertia(
            fn (AssertableInertia $p) => $p->where('solicitudes', [])
        );

        $this->actingAs($intruso)->get(route('portal.datos-fiscales'))->assertInertia(
            fn (AssertableInertia $p) => $p->where('datos', null)->where('completos', false)
        );
    }

    /** El panel operativo no es para miembros, y el portal no es para operativos. */
    public function test_los_portales_no_se_cruzan(): void
    {
        [$miembro] = $this->miembroConPlan();
        $admin = User::factory()->admin()->create();

        foreach (['portal.perfil', 'portal.accesos', 'portal.asesoria', 'portal.datos-fiscales'] as $ruta) {
            $this->actingAs($admin)->get(route($ruta))->assertForbidden();
        }

        $this->actingAs($miembro)->get(route('dashboard'))->assertForbidden();
    }

    // ── 2.2 · Perfil ────────────────────────────────────────────────────────

    public function test_el_perfil_guarda_el_contacto_de_emergencia(): void
    {
        [$user] = $this->miembroConPlan();

        $this->actingAs($user)->patch(route('portal.perfil.update'), [
            'name'                           => 'Ana López',
            'telefono'                       => '9994615676',
            'contacto_emergencia_nombre'     => 'Marta López',
            'contacto_emergencia_telefono'   => '9991234567',
            'contacto_emergencia_parentesco' => 'Hermana',
            'notif_reservas'                 => true,
            'notif_membresia'                => false,
            'notif_comunidad'                => true,
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Marta López', $user->contacto_emergencia_nombre);
        $this->assertFalse($user->notif_membresia);
    }

    /** Un contacto de emergencia sin teléfono aparenta estar y no sirve. */
    public function test_un_contacto_de_emergencia_sin_telefono_se_rechaza(): void
    {
        [$user] = $this->miembroConPlan();

        $this->actingAs($user)->patch(route('portal.perfil.update'), [
            'name'                         => 'Ana López',
            'contacto_emergencia_nombre'   => 'Marta López',
            'contacto_emergencia_telefono' => '',
        ])->assertSessionHasErrors('contacto_emergencia_telefono');
    }
}
