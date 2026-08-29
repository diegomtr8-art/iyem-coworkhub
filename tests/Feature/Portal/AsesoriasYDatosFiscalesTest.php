<?php

namespace Tests\Feature\Portal;

use App\Enums\BolsaDeHoras;
use App\Enums\EstadoAsesoria;
use App\Enums\MotivoMovimiento;
use App\Models\DatosFiscales;
use App\Models\MovimientoHoras;
use App\Models\Plane;
use App\Models\SolicitudAsesoria;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Asesorias\GestorDeAsesorias;
use App\Servicios\Horas\LibroDeHoras;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Fases 1.2 y 1.3 — asesoría IYEM y datos fiscales.
 */
class AsesoriasYDatosFiscalesTest extends TestCase
{
    use RefreshDatabase;

    private function nodoProConAsesoria(): Suscripcion
    {
        // Nodo Pro incluye 4 h de asesoría al mes, máximo 1 h al día.
        $plan = Plane::factory()->nodoPro()->create([
            'horas_asesoria_mes'     => 4,
            'max_horas_asesoria_dia' => 1,
        ]);

        $user = User::factory()->miembro()->create();

        return Suscripcion::factory()->delPlan($plan)->create([
            'user_id'      => $user->id,
            'fecha_inicio' => today()->subDays(5),
            'fecha_fin'    => today()->addMonths(6),
        ])->load('plan');
    }

    // ── Asesoría (Fase 1.2) ─────────────────────────────────────────────────

    /** La regla que ordena todo: pedir no cuesta; confirmar sí. */
    public function test_solicitar_no_descuenta_horas(): void
    {
        $suscripcion = $this->nodoProConAsesoria();
        $gestor      = app(GestorDeAsesorias::class);

        $gestor->solicitar($suscripcion, 'Modelo de negocio', today()->addDays(3)->toDateString(), 'Por la mañana');

        $this->assertSame(0.0, (float) $suscripcion->refresh()->horas_asesoria_usadas);
        $this->assertSame(0, MovimientoHoras::count());
        $this->assertSame(4.0, app(LibroDeHoras::class)->saldoDelCiclo($suscripcion, BolsaDeHoras::Asesoria));
    }

    public function test_confirmar_descuenta_las_horas(): void
    {
        $suscripcion = $this->nodoProConAsesoria();
        $gestor      = app(GestorDeAsesorias::class);
        $operativo   = User::factory()->admin()->create();

        $solicitud = $gestor->solicitar(
            $suscripcion, 'Modelo de negocio', today()->addDays(3)->toDateString(), 'Por la mañana'
        );

        $gestor->confirmar(
            solicitud: $solicitud,
            operativo: $operativo,
            fechaConfirmada: today()->addDays(3)->setTime(10, 0)->toDateTimeString(),
            asesorNombre: 'Lic. Ramírez',
        );

        $this->assertSame(EstadoAsesoria::Confirmada, $solicitud->refresh()->estado);
        $this->assertSame(1.0, (float) $suscripcion->refresh()->horas_asesoria_usadas);

        $movimiento = MovimientoHoras::first();
        $this->assertSame(MotivoMovimiento::Asesoria, $movimiento->motivo);
        $this->assertSame($solicitud->id, $movimiento->solicitud_asesoria_id);
        $this->assertSame($operativo->id, $movimiento->creado_por_user_id);
    }

    public function test_rechazar_no_descuenta_nada(): void
    {
        $suscripcion = $this->nodoProConAsesoria();
        $gestor      = app(GestorDeAsesorias::class);
        $operativo   = User::factory()->admin()->create();

        $solicitud = $gestor->solicitar(
            $suscripcion, 'Tema', today()->addDays(3)->toDateString(), 'Por la tarde'
        );

        $gestor->rechazar($solicitud, $operativo, 'No hay asesor disponible esa semana.');

        $this->assertSame(EstadoAsesoria::Rechazada, $solicitud->refresh()->estado);
        $this->assertSame(0.0, (float) $suscripcion->refresh()->horas_asesoria_usadas);
        $this->assertSame(0, MovimientoHoras::count());
    }

    public function test_rechazar_sin_motivo_no_se_puede(): void
    {
        $suscripcion = $this->nodoProConAsesoria();
        $gestor      = app(GestorDeAsesorias::class);

        $solicitud = $gestor->solicitar(
            $suscripcion, 'Tema', today()->addDays(3)->toDateString(), 'Por la tarde'
        );

        $this->expectException(ValidationException::class);
        $gestor->rechazar($solicitud, User::factory()->admin()->create(), '   ');
    }

    public function test_cancelar_a_tiempo_una_confirmada_devuelve_las_horas(): void
    {
        $suscripcion = $this->nodoProConAsesoria();
        $gestor      = app(GestorDeAsesorias::class);
        $operativo   = User::factory()->admin()->create();

        $solicitud = $gestor->solicitar(
            $suscripcion, 'Tema', today()->addDays(5)->toDateString(), 'Por la mañana'
        );

        $gestor->confirmar(
            solicitud: $solicitud,
            operativo: $operativo,
            fechaConfirmada: today()->addDays(5)->setTime(10, 0)->toDateTimeString(),
            asesorNombre: 'Lic. Ramírez',
        );

        $this->assertSame(1.0, (float) $suscripcion->refresh()->horas_asesoria_usadas);

        $gestor->cancelar($solicitud->refresh(), $suscripcion->user);

        $this->assertSame(EstadoAsesoria::Cancelada, $solicitud->refresh()->estado);
        $this->assertSame(0.0, (float) $suscripcion->refresh()->horas_asesoria_usadas);
    }

    public function test_el_tope_de_una_hora_diaria_de_asesoria_se_respeta(): void
    {
        $suscripcion = $this->nodoProConAsesoria();
        $gestor      = app(GestorDeAsesorias::class);
        $operativo   = User::factory()->admin()->create();
        $dia         = today()->addDays(3);

        $primera = $gestor->solicitar($suscripcion, 'Uno', $dia->toDateString(), 'Mañana');
        $gestor->confirmar(
            solicitud: $primera,
            operativo: $operativo,
            fechaConfirmada: $dia->copy()->setTime(10, 0)->toDateTimeString(),
            asesorNombre: 'Lic. Ramírez',
        );

        // El tope se avisa ya al **solicitar**: dejar pedir algo que se va a
        // rechazar por regla solo genera una decepción y trabajo para recepción.
        try {
            $gestor->solicitar($suscripcion, 'Dos', $dia->toDateString(), 'Tarde');
            $this->fail('La segunda solicitud del mismo día debió avisar del tope.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('dia_preferido', $e->errors());
        }

        // Y otro día distinto sí entra: el tope es diario, no del ciclo.
        $otroDia = today()->addDays(4);
        $tercera = $gestor->solicitar($suscripcion, 'Tres', $otroDia->toDateString(), 'Mañana');

        $gestor->confirmar(
            solicitud: $tercera,
            operativo: $operativo,
            fechaConfirmada: $otroDia->copy()->setTime(10, 0)->toDateTimeString(),
            asesorNombre: 'Lic. Ramírez',
        );

        $this->assertSame(2.0, (float) $suscripcion->refresh()->horas_asesoria_usadas);
    }

    public function test_un_plan_sin_asesoria_no_puede_solicitarla(): void
    {
        $plan = Plane::factory()->flex()->create();
        $user = User::factory()->miembro()->create();

        $suscripcion = Suscripcion::factory()->delPlan($plan)->create([
            'user_id'   => $user->id,
            'fecha_fin' => today()->addDays(30),
        ])->load('plan');

        $this->expectException(ValidationException::class);

        app(GestorDeAsesorias::class)->solicitar(
            $suscripcion, 'Tema', today()->addDays(2)->toDateString(), 'Mañana'
        );
    }

    public function test_confirmar_dos_veces_no_descuenta_dos_veces(): void
    {
        $suscripcion = $this->nodoProConAsesoria();
        $gestor      = app(GestorDeAsesorias::class);
        $operativo   = User::factory()->admin()->create();

        $solicitud = $gestor->solicitar(
            $suscripcion, 'Tema', today()->addDays(3)->toDateString(), 'Mañana'
        );

        $cuando = today()->addDays(3)->setTime(10, 0)->toDateTimeString();

        $gestor->confirmar($solicitud, $operativo, $cuando, 'Lic. Ramírez');

        try {
            $gestor->confirmar($solicitud->refresh(), $operativo, $cuando, 'Lic. Ramírez');
            $this->fail('La segunda confirmación debió rechazarse.');
        } catch (ValidationException) {
            // Esperado.
        }

        $this->assertSame(1.0, (float) $suscripcion->refresh()->horas_asesoria_usadas);
        $this->assertSame(1, SolicitudAsesoria::count());
    }

    // ── Datos fiscales (Fase 1.3) ───────────────────────────────────────────

    public static function rfcsValidos(): array
    {
        return [
            'persona física'  => ['XAXX010101000', 13],
            'persona moral'   => ['ABC010101AB1', 12],
            'con ñ'           => ['ÑAXX010101000', 13],
            'con ampersand'   => ['A&C010101AB1', 12],
        ];
    }

    /** @dataProvider rfcsValidos */
    public function test_un_rfc_bien_formado_pasa(string $rfc, int $longitud): void
    {
        $this->assertTrue(DatosFiscales::rfcTieneFormatoValido($rfc), "«{$rfc}» debió ser válido.");
        $this->assertSame($longitud, mb_strlen(DatosFiscales::normalizarRfc($rfc)));
    }

    public static function rfcsInvalidos(): array
    {
        return [
            'muy corto'          => ['XAXX0101'],
            'muy largo'          => ['XAXX0101010001'],
            'fecha imposible'    => ['XAXX130229000'],
            'mes imposible'      => ['XAXX011301000'],
            'sin homoclave'      => ['XAXX010101'],
            'letras en la fecha' => ['XAXXABCDEF000'],
            'vacío'              => [''],
        ];
    }

    /** @dataProvider rfcsInvalidos */
    public function test_un_rfc_mal_formado_se_rechaza(string $rfc): void
    {
        $this->assertFalse(DatosFiscales::rfcTieneFormatoValido($rfc), "«{$rfc}» no debió pasar.");
    }

    public function test_el_rfc_se_guarda_normalizado(): void
    {
        $user = User::factory()->miembro()->create();

        $datos = DatosFiscales::create([
            'user_id'           => $user->id,
            'rfc'               => '  xaxx-010101-000  ',
            'razon_social'      => 'Público en general',
            'regimen_fiscal'    => '612',
            'uso_cfdi'          => 'G03',
            'codigo_postal'     => '97110',
            'email_facturacion' => 'facturas@ejemplo.mx',
        ]);

        $this->assertSame(
            'XAXX010101000',
            $datos->refresh()->rfc,
            'Sin normalizar, «xaxx-010101-000» y «XAXX010101000» son dos contribuyentes distintos.'
        );
        $this->assertTrue($datos->estanCompletos());
        $this->assertSame('fisica', $datos->tipoDePersona());
    }

    public function test_un_rfc_de_doce_posiciones_es_persona_moral(): void
    {
        $user = User::factory()->miembro()->create();

        $datos = DatosFiscales::create([
            'user_id'           => $user->id,
            'rfc'               => 'ABC010101AB1',
            'razon_social'      => 'Empresa SA de CV',
            'regimen_fiscal'    => '601',
            'uso_cfdi'          => 'G03',
            'codigo_postal'     => '97110',
            'email_facturacion' => 'facturas@empresa.mx',
        ]);

        $this->assertTrue($datos->esPersonaMoral());
        $this->assertSame('moral', $datos->tipoDePersona());
        $this->assertSame('General de Ley Personas Morales', $datos->regimenLegible());
        $this->assertSame('Gastos en general', $datos->usoCfdiLegible());
    }

    /** Dato personal sensible: un miembro no puede ver los de otro. */
    public function test_un_miembro_no_puede_ver_los_datos_fiscales_de_otro(): void
    {
        $duenio  = User::factory()->miembro()->create();
        $intruso = User::factory()->miembro()->create();
        $staff   = User::factory()->staff()->create();
        $admin   = User::factory()->admin()->create();

        $datos = DatosFiscales::create([
            'user_id'           => $duenio->id,
            'rfc'               => 'XAXX010101000',
            'razon_social'      => 'Público en general',
            'regimen_fiscal'    => '612',
            'uso_cfdi'          => 'G03',
            'codigo_postal'     => '97110',
            'email_facturacion' => 'facturas@ejemplo.mx',
        ]);

        $this->assertTrue($duenio->can('view', $datos));
        $this->assertFalse($intruso->can('view', $datos));

        // Recepción opera el día a día y no necesita el RFC de nadie.
        $this->assertFalse($staff->can('view', $datos), 'Recepción no debe ver datos fiscales.');
        $this->assertTrue($admin->can('view', $datos));

        $this->assertFalse($staff->can('exportar', DatosFiscales::class));
        $this->assertTrue($admin->can('exportar', DatosFiscales::class));
    }
}
