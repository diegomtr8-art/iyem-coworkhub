<?php

namespace Tests\Feature\Panel;

use App\Enums\BolsaDeHoras;
use App\Enums\EstadoAsesoria;
use App\Enums\EstadoRentaSalon;
use App\Enums\MotivoMovimiento;
use App\Models\Asesor;
use App\Models\BloqueoEspacio;
use App\Models\DatosFiscales;
use App\Models\EntradaBitacora;
use App\Models\Espacio;
use App\Models\MovimientoHoras;
use App\Models\Plane;
use App\Models\RentaSalon;
use App\Models\Reserva;
use App\Models\SolicitudAsesoria;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Horas\LibroDeHoras;
use App\Servicios\Salones\CotizadorDeSalon;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Fase 3 — el portal operativo.
 *
 * Lo que más importa aquí no son las pantallas sino **quién puede hacer qué**:
 * recepción opera el día a día pero no toca precios, planes, reportes ni datos
 * fiscales.
 */
class PanelOperativoTest extends TestCase
{
    use RefreshDatabase;

    private function miembroConPlan(): array
    {
        $plan = Plane::factory()->nodoPro()->create([
            'horas_asesoria_mes' => 4, 'max_horas_asesoria_dia' => 1,
        ]);

        $user = User::factory()->miembro()->create();

        $suscripcion = Suscripcion::factory()->delPlan($plan)->create([
            'user_id'      => $user->id,
            'fecha_inicio' => today()->subDays(5),
            'fecha_fin'    => today()->addMonths(3),
        ]);

        return [$user, $suscripcion, $plan];
    }

    private function lunes(): string
    {
        return today()->next(Carbon::MONDAY)->toDateString();
    }

    // ── Permisos: la parte que de verdad protege ────────────────────────────

    public static function rutasSoloDeAdmin(): array
    {
        return [
            'reportes'  => ['reportes.index'],
            'planes'    => ['planes.index'],
            'espacios'  => ['espacios.index'],
            'bitácora'  => ['bitacora.index'],
            'facturas'  => ['facturas.index'],
            'asesores'  => ['asesores.index'],
        ];
    }

    /** @dataProvider rutasSoloDeAdmin */
    public function test_recepcion_no_entra_a_lo_que_es_de_administracion(string $ruta): void
    {
        $this->actingAs(User::factory()->staff()->create())->get(route($ruta))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route($ruta))->assertOk();
    }

    public static function rutasDeRecepcion(): array
    {
        return [
            'tablero'   => ['dashboard'],
            'miembros'  => ['miembros.index'],
            'agenda'    => ['agenda.index'],
            'asesorías' => ['asesorias.index'],
            'salones'   => ['salones.index'],
            'check-ins' => ['checkins.index'],
        ];
    }

    /** @dataProvider rutasDeRecepcion */
    public function test_recepcion_si_entra_a_la_operacion_diaria(string $ruta): void
    {
        $this->actingAs(User::factory()->staff()->create())->get(route($ruta))->assertOk();
    }

    /** El prompt lo pide explícito: recepción ajusta horas. */
    public function test_recepcion_puede_ajustar_horas_pero_no_editar_planes(): void
    {
        [$miembro, $suscripcion] = $this->miembroConPlan();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('miembros.ajustar-horas', $miembro), [
            'suscripcion_id' => $suscripcion->id,
            'bolsa'          => BolsaDeHoras::Sala->value,
            'horas'          => 2,
            'motivo'         => 'Se cayó el internet y no pudo usar la sala.',
        ])->assertSessionHasNoErrors();

        // Pero no toca la configuración del negocio.
        $this->actingAs($staff)->get(route('planes.index'))->assertForbidden();
    }

    /** Los datos fiscales son sensibles: ni recepción ni otro miembro. */
    public function test_recepcion_no_ve_los_datos_fiscales_en_la_ficha(): void
    {
        [$miembro] = $this->miembroConPlan();

        DatosFiscales::create([
            'user_id' => $miembro->id, 'rfc' => 'XAXX010101000',
            'razon_social' => 'Público en general', 'regimen_fiscal' => '612',
            'uso_cfdi' => 'G03', 'codigo_postal' => '97110',
            'email_facturacion' => 'privado@ejemplo.mx',
        ]);

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('miembros.show', $miembro))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('puedeVerFiscales', false)
                ->where('datosFiscales', null));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('miembros.show', $miembro))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('puedeVerFiscales', true)
                ->where('datosFiscales.rfc', 'XAXX010101000'));
    }

    /** Y consultarlos queda registrado. */
    public function test_consultar_datos_fiscales_de_un_miembro_queda_en_la_bitacora(): void
    {
        [$miembro] = $this->miembroConPlan();
        $admin = User::factory()->admin()->create();

        DatosFiscales::create([
            'user_id' => $miembro->id, 'rfc' => 'XAXX010101000',
            'razon_social' => 'Público en general', 'regimen_fiscal' => '612',
            'uso_cfdi' => 'G03', 'codigo_postal' => '97110',
            'email_facturacion' => 'privado@ejemplo.mx',
        ]);

        $this->actingAs($admin)->get(route('miembros.show', $miembro));

        $this->assertDatabaseHas('bitacora_operacion', [
            'accion'         => 'consulta_datos_fiscales',
            'actor_user_id'  => $admin->id,
            'sujeto_user_id' => $miembro->id,
        ]);
    }

    // ── 3.2 · Ajuste de horas ───────────────────────────────────────────────

    /** Reponer horas **baja** el consumo y deja movimiento y bitácora. */
    public function test_reponer_horas_escribe_movimiento_y_bitacora(): void
    {
        [$miembro, $suscripcion] = $this->miembroConPlan();
        $staff = User::factory()->staff()->create();

        app(LibroDeHoras::class)->registrar(
            suscripcion: $suscripcion, bolsa: BolsaDeHoras::Sala, cantidad: 6,
            motivo: MotivoMovimiento::AjusteManual, nota: 'Consumo previo.',
        );

        $this->actingAs($staff)->post(route('miembros.ajustar-horas', $miembro), [
            'suscripcion_id' => $suscripcion->id,
            'bolsa'          => BolsaDeHoras::Sala->value,
            'horas'          => 2,
            'motivo'         => 'Se le cayó el internet durante su sesión.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(4.0, (float) $suscripcion->refresh()->horas_sala_usadas);

        $movimiento = MovimientoHoras::latest('id')->first();
        $this->assertSame(-2.0, $movimiento->cantidad);
        $this->assertSame($staff->id, $movimiento->creado_por_user_id);

        $this->assertDatabaseHas('bitacora_operacion', [
            'accion'        => 'ajuste_horas',
            'actor_user_id' => $staff->id,
        ]);
    }

    public function test_un_ajuste_sin_motivo_se_rechaza(): void
    {
        [$miembro, $suscripcion] = $this->miembroConPlan();

        $this->actingAs(User::factory()->staff()->create())
            ->post(route('miembros.ajustar-horas', $miembro), [
                'suscripcion_id' => $suscripcion->id,
                'bolsa'          => BolsaDeHoras::Sala->value,
                'horas'          => 2,
                'motivo'         => '',
            ])->assertSessionHasErrors('motivo');

        $this->assertSame(0, MovimientoHoras::count());
    }

    // ── 3.3 · Membresías ────────────────────────────────────────────────────

    /** Decisión de Nódico: cambiar de plan abre un ciclo nuevo desde cero. */
    public function test_cambiar_de_plan_abre_un_ciclo_nuevo_con_las_bolsas_completas(): void
    {
        [$miembro, $suscripcion] = $this->miembroConPlan();
        $admin = User::factory()->admin()->create();

        app(LibroDeHoras::class)->registrar(
            suscripcion: $suscripcion, bolsa: BolsaDeHoras::Sala, cantidad: 8,
            motivo: MotivoMovimiento::AjusteManual, nota: 'Consumo del ciclo viejo.',
        );

        $match = Plane::factory()->nodoMatch()->create();

        $this->actingAs($admin)->post(route('miembros.cambiar-plan', $miembro), [
            'suscripcion_id' => $suscripcion->id,
            'plan_id'        => $match->id,
            'motivo'         => 'Se muda con su socia y necesitan dos accesos.',
        ])->assertSessionHasNoErrors();

        $nueva = $miembro->refresh()->suscripciones()->where('estatus', 'Activa')->latest('fecha_inicio')->first();

        $this->assertSame($match->id, $nueva->plan_id);
        $this->assertSame(0.0, (float) $nueva->horas_sala_usadas, 'El ciclo nuevo arranca en cero.');
        $this->assertSame(20.0, $nueva->load('plan')->horasSalaRestantes());

        // La anterior se cierra: dos activas a la vez darían saldos distintos
        // según qué consulta gane.
        $this->assertSame('Vencida', $suscripcion->refresh()->estatus);

        // Y el consumo abandonado queda anotado, que es la contrapartida.
        $entrada = EntradaBitacora::where('accion', 'cambio_plan')->latest('id')->first();
        $this->assertNotNull($entrada);
        // El contexto viaja como JSON: un 8.0 vuelve como entero.
        $this->assertEqualsWithDelta(8, $entrada->contexto['consumo_abandonado']['sala'], 0.001);
    }

    public function test_cambiar_de_plan_sin_motivo_se_rechaza(): void
    {
        [$miembro, $suscripcion] = $this->miembroConPlan();
        $otro = Plane::factory()->nodoMatch()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('miembros.cambiar-plan', $miembro), [
                'suscripcion_id' => $suscripcion->id,
                'plan_id'        => $otro->id,
                'motivo'         => '',
            ])->assertSessionHasErrors('motivo');
    }

    /** El alta cierra la anterior y abre el ciclo al momento. */
    public function test_el_alta_manual_cierra_la_anterior_y_abre_ciclo(): void
    {
        [$miembro, $anterior] = $this->miembroConPlan();
        $admin = User::factory()->admin()->create();
        $flex  = Plane::factory()->flex()->create();

        $this->actingAs($admin)->post(route('miembros.suscripcion', $miembro), [
            'plan_id'       => $flex->id,
            'precio_pagado' => 249,
            'nota'          => 'Pagó en efectivo.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Vencida', $anterior->refresh()->estatus);
        $this->assertSame(1, $miembro->refresh()->suscripciones()->where('estatus', 'Activa')->count());

        // Ventana de 30 días para los planes por día.
        $nueva = $miembro->suscripciones()->where('estatus', 'Activa')->first();
        $this->assertSame(today()->addDays(30)->toDateString(), $nueva->fecha_fin->toDateString());
    }

    // ── 3.4 · Agenda y sobrecupo ────────────────────────────────────────────

    /** Sin autorización explícita, el sobrecupo se rechaza igual que en el portal. */
    public function test_el_sobrecupo_se_rechaza_si_no_se_autoriza(): void
    {
        [$miembro, $suscripcion] = $this->miembroConPlan();
        $sala  = Espacio::factory()->salaJuntas()->create();
        $staff = User::factory()->staff()->create();

        // Sin saldo.
        app(LibroDeHoras::class)->registrar(
            suscripcion: $suscripcion, bolsa: BolsaDeHoras::Sala, cantidad: 10,
            motivo: MotivoMovimiento::AjusteManual, nota: 'Bolsa agotada.',
        );

        $this->actingAs($staff)->post(route('agenda.reservar'), [
            'user_id'     => $miembro->id,
            'espacio_id'  => $sala->id,
            'fecha'       => $this->lunes(),
            'hora_inicio' => '10:00',
            'hora_fin'    => '11:00',
        ])->assertSessionHasErrors('sobrecupo');

        $this->assertSame(0, Reserva::count());
    }

    /** Autorizado y con motivo, pasa y deja constancia de quién lo firmó. */
    public function test_el_sobrecupo_autorizado_pasa_y_queda_en_la_bitacora(): void
    {
        [$miembro, $suscripcion] = $this->miembroConPlan();
        $sala  = Espacio::factory()->salaJuntas()->create();
        $staff = User::factory()->staff()->create();

        app(LibroDeHoras::class)->registrar(
            suscripcion: $suscripcion, bolsa: BolsaDeHoras::Sala, cantidad: 10,
            motivo: MotivoMovimiento::AjusteManual, nota: 'Bolsa agotada.',
        );

        $this->actingAs($staff)->post(route('agenda.reservar'), [
            'user_id'            => $miembro->id,
            'espacio_id'         => $sala->id,
            'fecha'              => $this->lunes(),
            'hora_inicio'        => '10:00',
            'hora_fin'           => '11:00',
            'autoriza_sobrecupo' => true,
            'motivo_sobrecupo'   => 'Junta con un inversionista; la sala estaba vacía.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Reserva::count());

        $entrada = EntradaBitacora::where('accion', 'sobrecupo')->first();
        $this->assertNotNull($entrada, 'Un sobrecupo que nadie firma es dinero regalado sin enterarse.');
        $this->assertSame($staff->id, $entrada->actor_user_id);
        $this->assertStringContainsString('inversionista', $entrada->motivo);
    }

    /** Autorizar el sobrecupo sin escribir por qué no vale. */
    public function test_el_sobrecupo_exige_motivo(): void
    {
        [$miembro, $suscripcion] = $this->miembroConPlan();
        $sala = Espacio::factory()->salaJuntas()->create();

        app(LibroDeHoras::class)->registrar(
            suscripcion: $suscripcion, bolsa: BolsaDeHoras::Sala, cantidad: 10,
            motivo: MotivoMovimiento::AjusteManual, nota: 'Bolsa agotada.',
        );

        $this->actingAs(User::factory()->staff()->create())->post(route('agenda.reservar'), [
            'user_id'            => $miembro->id,
            'espacio_id'         => $sala->id,
            'fecha'              => $this->lunes(),
            'hora_inicio'        => '10:00',
            'hora_fin'           => '11:00',
            'autoriza_sobrecupo' => true,
            'motivo_sobrecupo'   => '',
        ])->assertSessionHasErrors('motivo_sobrecupo');
    }

    /** Recepción se salta la antelación mínima, pero no el calendario. */
    public function test_recepcion_no_puede_reservar_un_domingo(): void
    {
        [$miembro] = $this->miembroConPlan();
        $sala = Espacio::factory()->salaJuntas()->create();

        $this->actingAs(User::factory()->staff()->create())->post(route('agenda.reservar'), [
            'user_id'     => $miembro->id,
            'espacio_id'  => $sala->id,
            'fecha'       => today()->next(Carbon::SUNDAY)->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin'    => '11:00',
        ])->assertSessionHasErrors('fecha');
    }

    public function test_un_bloqueo_desde_el_panel_impide_reservar_ese_horario(): void
    {
        [$miembro] = $this->miembroConPlan();
        $sala  = Espacio::factory()->salaJuntas()->create();
        $staff = User::factory()->staff()->create();
        $lunes = $this->lunes();

        $this->actingAs($staff)->post(route('agenda.bloquear'), [
            'espacio_id'  => $sala->id,
            'fecha'       => $lunes,
            'hora_inicio' => '10:00',
            'hora_fin'    => '12:00',
            'motivo'      => 'Mantenimiento del aire acondicionado',
        ])->assertSessionHasNoErrors();

        $this->actingAs($staff)->post(route('agenda.reservar'), [
            'user_id'     => $miembro->id,
            'espacio_id'  => $sala->id,
            'fecha'       => $lunes,
            'hora_inicio' => '10:00',
            'hora_fin'    => '11:00',
        ])->assertSessionHasErrors();

        $this->assertSame(0, Reserva::count());
    }

    // ── 3.5 · Salones ───────────────────────────────────────────────────────

    public function test_el_cotizador_aplica_los_dos_tramos_de_coffee_break(): void
    {
        $cotizador = app(CotizadorDeSalon::class);
        $salon = Espacio::factory()->salon()->create();

        // Hasta 25 pax: $45.
        $c = $cotizador->cotizar(salon: $salon, horas: 4, conCoffee: true, coffeePersonas: 20);
        $this->assertSame(45.0, $c['coffee_precio_persona']);
        $this->assertSame(900.0, $c['subtotal_coffee']);
        $this->assertSame(2400.0, $c['subtotal_salon']);
        $this->assertSame(3300.0, $c['total']);

        // Desde 100 pax: $35.
        $c = $cotizador->cotizar(salon: $salon, horas: 4, conCoffee: true, coffeePersonas: 120);
        $this->assertSame(35.0, $c['coffee_precio_persona']);
        $this->assertSame(4200.0, $c['subtotal_coffee']);
    }

    /**
     * El tramo sin tarifa no se inventa: se pide el precio. Un precio inventado
     * se cobra de verdad.
     */
    public function test_entre_los_dos_tramos_el_coffee_pide_precio_a_mano(): void
    {
        $cotizador = app(CotizadorDeSalon::class);
        $salon = Espacio::factory()->salon()->create();

        $c = $cotizador->cotizar(salon: $salon, horas: 4, conCoffee: true, coffeePersonas: 60);

        $this->assertTrue($c['coffee_requiere_precio']);
        $this->assertSame(0.0, $c['subtotal_coffee']);
        $this->assertNotNull($c['coffee_nota']);

        // Con el precio que escribe recepción, ya cotiza.
        $c = $cotizador->cotizar(salon: $salon, horas: 4, conCoffee: true, coffeePersonas: 60, precioCoffeePersona: 40);
        $this->assertFalse($c['coffee_requiere_precio']);
        $this->assertSame(2400.0, $c['subtotal_coffee']);
    }

    public function test_el_cotizador_avisa_si_el_montaje_no_admite_tanta_gente(): void
    {
        $salon = Espacio::factory()->salon()->create();  // herradura 45, auditorio 120

        $avisos = app(CotizadorDeSalon::class)->avisosDeAforo(
            $salon, \App\Enums\TipoMontaje::Herradura, 80,
        );

        $this->assertNotEmpty($avisos);
        $this->assertStringContainsString('45', $avisos[0]);
        $this->assertStringContainsString('Auditorio', $avisos[1] ?? '');
    }

    /** Una cotización no aparta la fecha; una renta confirmada sí. */
    public function test_confirmar_una_renta_aparta_el_salon_en_la_agenda(): void
    {
        $salon = Espacio::factory()->salon()->create();
        $staff = User::factory()->staff()->create();
        $lunes = $this->lunes();

        $base = [
            'espacio_id' => $salon->id, 'cliente_nombre' => 'CANIETI',
            'fecha' => $lunes, 'hora_inicio' => '09:00', 'hora_fin' => '13:00',
            'personas' => 80, 'montaje' => 'auditorio',
        ];

        // Cotización: no bloquea.
        $this->actingAs($staff)->post(route('salones.store'), [...$base, 'estado' => 'cotizacion'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, BloqueoEspacio::count());

        // Confirmada: sí bloquea.
        $this->actingAs($staff)->post(route('salones.store'), [
            ...$base, 'cliente_nombre' => 'IYEM', 'hora_inicio' => '14:00', 'hora_fin' => '18:00',
            'estado' => 'confirmada',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, BloqueoEspacio::count());
        $this->assertNotNull(RentaSalon::where('cliente_nombre', 'IYEM')->first()->bloqueo_id);
    }

    /** El total nunca llega del navegador: se recalcula al guardar. */
    public function test_el_total_se_recalcula_en_el_servidor(): void
    {
        $salon = Espacio::factory()->salon()->create();

        $this->actingAs(User::factory()->staff()->create())->post(route('salones.store'), [
            'espacio_id' => $salon->id, 'cliente_nombre' => 'Cliente',
            'fecha' => $this->lunes(), 'hora_inicio' => '09:00', 'hora_fin' => '13:00',
            'estado' => 'cotizacion',
            // Intento de mandar un total falso: ni siquiera se lee.
            'total' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2400.0, (float) RentaSalon::first()->total, '4 h × $600.');
    }

    // ── 3.7 · Asesorías ─────────────────────────────────────────────────────

    public function test_confirmar_desde_el_panel_descuenta_y_congela_el_nombre_del_asesor(): void
    {
        [$miembro, $suscripcion] = $this->miembroConPlan();
        $staff  = User::factory()->staff()->create();
        $asesor = Asesor::create(['nombre' => 'Lic. Ramírez', 'especialidad' => 'Finanzas']);

        $solicitud = SolicitudAsesoria::create([
            'user_id' => $miembro->id, 'suscripcion_id' => $suscripcion->id,
            'tema' => 'Revisar precios', 'dia_preferido' => today()->addDays(3),
            'horario_preferido' => 'Mañana', 'horas' => 1,
        ]);

        $this->actingAs($staff)->post(route('asesorias.confirmar', $solicitud), [
            'asesor_id'        => $asesor->id,
            'fecha_confirmada' => today()->addDays(3)->setTime(10, 0)->toDateTimeString(),
        ])->assertSessionHasNoErrors();

        $solicitud->refresh();
        $this->assertSame(EstadoAsesoria::Confirmada, $solicitud->estado);
        $this->assertSame(1.0, (float) $suscripcion->refresh()->horas_asesoria_usadas);

        // El nombre queda congelado: si el asesor se da de baja, el histórico
        // sigue diciendo quién dio la asesoría.
        $this->assertSame('Lic. Ramírez', $solicitud->asesor_nombre);

        $asesor->delete();
        $this->assertSame('Lic. Ramírez', $solicitud->refresh()->asesorLegible());
    }

    /**
     * Regresión: la bandeja cargaba `plan:id,nombre` y los campos de cupo se
     * quedaban en null, con lo que **el saldo salía siempre en cero** y
     * recepción veía que nadie tenía horas. Un `select` parcial sobre una
     * relación que después se usa para calcular es una trampa silenciosa.
     */
    public function test_la_bandeja_ensena_el_saldo_real_de_asesoria(): void
    {
        [$miembro, $suscripcion] = $this->miembroConPlan();

        app(LibroDeHoras::class)->registrar(
            suscripcion: $suscripcion, bolsa: BolsaDeHoras::Asesoria, cantidad: 1,
            motivo: MotivoMovimiento::AjusteManual, nota: 'Una sesión ya usada.',
        );

        SolicitudAsesoria::create([
            'user_id' => $miembro->id, 'suscripcion_id' => $suscripcion->id,
            'tema' => 'Revisar precios', 'dia_preferido' => today()->addDays(3),
            'horario_preferido' => 'Mañana', 'horas' => 1,
        ]);

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('asesorias.index'))
            ->assertInertia(function (AssertableInertia $p) {
                $saldo = $p->toArray()['props']['solicitudes']['data'][0]['saldo_asesoria'];

                $this->assertEqualsWithDelta(
                    3, $saldo, 0.001,
                    'Nodo Pro incluye 4 h y ya usó 1: el saldo es 3, no 0.',
                );
            });
    }

    public function test_rechazar_desde_el_panel_no_descuenta_y_exige_motivo(): void
    {
        [$miembro, $suscripcion] = $this->miembroConPlan();
        $staff = User::factory()->staff()->create();

        $solicitud = SolicitudAsesoria::create([
            'user_id' => $miembro->id, 'suscripcion_id' => $suscripcion->id,
            'tema' => 'Tema', 'dia_preferido' => today()->addDays(3),
            'horario_preferido' => 'Mañana', 'horas' => 1,
        ]);

        $this->actingAs($staff)->post(route('asesorias.rechazar', $solicitud), ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->actingAs($staff)->post(route('asesorias.rechazar', $solicitud), [
            'motivo' => 'No hay asesor disponible esa semana.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(EstadoAsesoria::Rechazada, $solicitud->refresh()->estado);
        $this->assertSame(0.0, (float) $suscripcion->refresh()->horas_asesoria_usadas);
    }

    // ── 3.9 y 3.10 · Facturación y reportes ─────────────────────────────────

    public function test_la_exportacion_fiscal_es_solo_de_admin_y_queda_en_la_bitacora(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('facturas.exportar'))
            ->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('facturas.exportar'))->assertOk();

        $this->assertDatabaseHas('bitacora_operacion', [
            'accion'        => 'exportacion_fiscal',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_los_reportes_traen_los_seis_informes(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Reportes/Index')
                ->has('ocupacion')
                ->has('porFranja')
                ->has('consumoPorPlan')
                ->has('ingresos.por_plan')
                ->has('noShow.tasa_pct')
                ->has('enRiesgo'));
    }

    /** El informe con el que todavía se puede actuar. */
    public function test_un_miembro_activo_que_lleva_semanas_sin_venir_sale_en_riesgo(): void
    {
        [$ausente] = $this->miembroConPlan();
        [$asiduo]  = $this->miembroConPlan();

        $espacio = Espacio::factory()->coworking()->create();

        // El asiduo vino ayer; el ausente, nunca.
        $asiduo->checkins()->create([
            'espacio_id' => $espacio->id,
            'hora_entrada' => now()->subDay(),
            'hora_salida' => now()->subDay()->addHours(4),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('reportes.index'))
            ->assertInertia(function (AssertableInertia $p) use ($ausente, $asiduo) {
                $nombres = collect($p->toArray()['props']['enRiesgo'])->pluck('miembro');

                $this->assertContains($ausente->name, $nombres);
                $this->assertNotContains($asiduo->name, $nombres);
            });
    }

    // ── 3.1 · Buscador global ───────────────────────────────────────────────

    public function test_el_buscador_encuentra_por_nombre_correo_y_telefono(): void
    {
        $miembro = User::factory()->miembro()->create([
            'name' => 'Ana Sofía Canul', 'email' => 'ana@ejemplo.mx', 'telefono' => '9994615676',
        ]);

        $staff = User::factory()->staff()->create();

        foreach (['Canul', 'ana@ejemplo', '9994615676', '999 461 5676'] as $consulta) {
            $resultados = $this->actingAs($staff)
                ->getJson(route('buscar.miembro', ['q' => $consulta]))
                ->assertOk()
                ->json('resultados');

            $this->assertNotEmpty($resultados, "«{$consulta}» no encontró a nadie.");
            $this->assertSame($miembro->id, $resultados[0]['id']);
        }
    }

    public function test_el_buscador_no_responde_a_un_miembro(): void
    {
        [$miembro] = $this->miembroConPlan();

        $this->actingAs($miembro)->getJson(route('buscar.miembro', ['q' => 'ana']))->assertForbidden();
    }

    // ── 3.11 · Bitácora ─────────────────────────────────────────────────────

    public function test_la_bitacora_separa_operacion_de_acceso(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('bitacora.index'))->assertInertia(
            fn (AssertableInertia $p) => $p->where('registro', 'operacion')->has('operacion')->where('acceso', null)
        );

        $this->actingAs($admin)->get(route('bitacora.index', ['registro' => 'acceso']))->assertInertia(
            fn (AssertableInertia $p) => $p->where('registro', 'acceso')->has('acceso')->where('operacion', null)
        );
    }
}
