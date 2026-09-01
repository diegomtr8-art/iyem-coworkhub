<?php

namespace Tests\Feature\Acceso;

use App\Enums\BolsaDeHoras;
use App\Enums\MotivoMovimiento;
use App\Models\Checkin;
use App\Models\Espacio;
use App\Models\EventoAcceso;
use App\Models\MovimientoHoras;
use App\Models\Plane;
use App\Models\Suscripcion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Fase 2 (y Fase 7 de pruebas) — ingesta de eventos del terminal Smart Pass.
 *
 * Se comprueba el contrato exacto del agente: firma HMAC obligatoria,
 * idempotencia por `origen_id`, extraños que no gastan nada, la hora real del
 * evento, el antirrebote y que un día se consume una sola vez por día natural.
 */
class EventosDeAccesoTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETO = 'clave-de-prueba-del-agente';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'acceso.agente_secreto'          => self::SECRETO,
            'acceso.antirrebote_segundos'    => 90,
            'acceso.replay_ventana_segundos' => 300,
        ]);
    }

    // ── Utilidades ──────────────────────────────────────────────────────────

    /** Firma y envía un cuerpo crudo, tal cual lo haría el agente. */
    private function enviarEventos(array $eventos, ?string $secreto = self::SECRETO, ?int $ts = null): TestResponse
    {
        return $this->enviar('/api/acceso/eventos', ['eventos' => $eventos], $secreto, $ts);
    }

    private function enviar(string $uri, array $payload, ?string $secreto, ?int $ts = null): TestResponse
    {
        $cuerpo = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ts ??= time();

        $headers = [
            'Content-Type'       => 'application/json',
            'Accept'             => 'application/json',
            'X-Agente-Timestamp' => (string) $ts,
        ];

        if ($secreto !== null) {
            $headers['X-Agente-Firma'] = hash_hmac('sha256', $ts . '.' . $cuerpo, $secreto);
        }

        return $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers), $cuerpo);
    }

    /** Un evento con valores por defecto de un reconocido; se sobrescribe lo que haga falta. */
    private function evento(array $over = []): array
    {
        return array_merge([
            'origen_id'     => 1,
            'ocurrido_en'   => CarbonImmutable::now('America/Merida')->toIso8601String(),
            'person_id'     => 100,
            'person_type'   => 1,
            'reconocido'    => true,
            'pass_type'     => 'face_2',
            'sub_pass_type' => null,
            'direction'     => 1,
            'device_id'     => 7,
            'device_key'    => 'terminal-1',
        ], $over);
    }

    /** Un miembro con Day-Pass (consume días) amarrado a un person_id de Smart Pass. */
    private function miembroAmarrado(int $personId = 100): array
    {
        // El área de coworking donde `RegistroDeAcceso` deja al miembro cuando el
        // evento no trae espacio (el caso del terminal de la puerta).
        Espacio::factory()->coworking()->create(['disponible' => true]);

        $plan = Plane::factory()->dayPass()->create();
        $user = User::factory()->miembro()->create();
        $user->smartpass_person_id = $personId;
        $user->save();

        $suscripcion = Suscripcion::factory()->delPlan($plan)->create([
            'user_id'      => $user->id,
            'fecha_inicio' => today()->subDays(3),
            'fecha_fin'    => today()->addMonth(),
        ]);

        return [$user, $suscripcion];
    }

    private function diasUsados(Suscripcion $s): int
    {
        return (int) $s->refresh()->dias_usados;
    }

    // ── Pruebas ─────────────────────────────────────────────────────────────

    public function test_sin_firma_valida_rechaza_y_no_procesa(): void
    {
        // Sin cabecera de firma.
        $this->enviarEventos([$this->evento()], secreto: null)->assertStatus(401);

        // Con firma calculada con otro secreto.
        $this->enviarEventos([$this->evento()], secreto: 'secreto-equivocado')->assertStatus(401);

        $this->assertSame(0, EventoAcceso::count());
        $this->assertSame(0, Checkin::count());
    }

    public function test_sin_secreto_configurado_rechaza_todo(): void
    {
        config(['acceso.agente_secreto' => '']);

        // Aunque la firma "cuadre" con secreto vacío, se rechaza igual.
        $this->enviarEventos([$this->evento()], secreto: '')->assertStatus(401);

        $this->assertSame(0, EventoAcceso::count());
    }

    public function test_evento_repetido_no_genera_dos_checkins(): void
    {
        [$user, $suscripcion] = $this->miembroAmarrado();

        $evento = $this->evento(['origen_id' => 555]);

        $this->enviarEventos([$evento])->assertOk();
        $this->enviarEventos([$evento])->assertOk(); // reintento del agente

        $this->assertSame(1, EventoAcceso::count());
        $this->assertSame(1, Checkin::where('user_id', $user->id)->count());
        $this->assertSame(1, $this->diasUsados($suscripcion));
    }

    public function test_extrano_no_genera_checkin_ni_consume_dia(): void
    {
        $evento = $this->evento([
            'origen_id'   => 900,
            'person_id'   => -1,
            'person_type' => -1,
            'reconocido'  => false,
            'direction'   => 3,
        ]);

        $this->enviarEventos([$evento])->assertOk();

        $this->assertSame(1, EventoAcceso::count());
        $this->assertTrue(EventoAcceso::first()->esExtrano());
        $this->assertSame(0, Checkin::count());
        $this->assertSame(0, MovimientoHoras::where('bolsa', BolsaDeHoras::Dias->value)->count());
    }

    public function test_evento_con_hora_vieja_entra_con_su_hora_real(): void
    {
        [$user] = $this->miembroAmarrado();

        // Ayer a las 10:00 en Mérida (−06:00) = ayer 16:00 en UTC.
        $local    = CarbonImmutable::yesterday('America/Merida')->setTime(10, 0);
        $esperado = $local->utc();

        $this->enviarEventos([$this->evento([
            'origen_id'   => 42,
            'ocurrido_en' => $local->toIso8601String(),
        ])])->assertOk();

        $checkin = Checkin::where('user_id', $user->id)->firstOrFail();

        $this->assertTrue(
            $esperado->equalTo($checkin->hora_entrada),
            "La entrada debía guardarse con la hora del evento ({$esperado}), no la de recepción."
        );
        $this->assertTrue($esperado->equalTo(EventoAcceso::firstOrFail()->ocurrido_en));
    }

    public function test_reconocido_amarrado_genera_checkin_pero_antirrebote_evita_el_segundo(): void
    {
        [$user, $suscripcion] = $this->miembroAmarrado();

        $t = CarbonImmutable::now('America/Merida')->setTime(9, 0);

        // Primer reconocimiento: check-in.
        $this->enviarEventos([$this->evento([
            'origen_id'   => 1,
            'ocurrido_en' => $t->toIso8601String(),
            'direction'   => 1,
        ])])->assertOk();

        // Segundo, 30 s después, misma dirección: dentro de la ventana → no crea otro.
        $this->enviarEventos([$this->evento([
            'origen_id'   => 2,
            'ocurrido_en' => $t->addSeconds(30)->toIso8601String(),
            'direction'   => 1,
        ])])->assertOk();

        $this->assertSame(2, EventoAcceso::count());
        $this->assertSame(1, Checkin::where('user_id', $user->id)->count());
        $this->assertSame(1, $this->diasUsados($suscripcion));
    }

    public function test_dos_entradas_el_mismo_dia_consumen_un_solo_dia(): void
    {
        [$user, $suscripcion] = $this->miembroAmarrado();

        $t = CarbonImmutable::now('America/Merida')->setTime(9, 0);

        // Dos entradas separadas más que la ventana de antirrebote (salió a comer y
        // volvió): dos check-ins, pero un solo día consumido.
        $this->enviarEventos([$this->evento([
            'origen_id'   => 10,
            'ocurrido_en' => $t->toIso8601String(),
            'direction'   => 1,
        ])])->assertOk();

        $this->enviarEventos([$this->evento([
            'origen_id'   => 11,
            'ocurrido_en' => $t->addHours(3)->toIso8601String(),
            'direction'   => 1,
        ])])->assertOk();

        $this->assertSame(2, Checkin::where('user_id', $user->id)->count());
        $this->assertSame(1, $this->diasUsados($suscripcion));
        $this->assertSame(
            1,
            MovimientoHoras::where('suscripcion_id', $suscripcion->id)
                ->where('bolsa', BolsaDeHoras::Dias->value)
                ->where('motivo', MotivoMovimiento::Acceso->value)
                ->count()
        );
    }

    public function test_reconocido_sin_amarre_se_guarda_pero_no_genera_checkin(): void
    {
        // Nadie tiene ese smartpass_person_id.
        $this->enviarEventos([$this->evento([
            'origen_id' => 77,
            'person_id' => 4242,
        ])])->assertOk();

        $this->assertSame(1, EventoAcceso::count());
        $this->assertNull(EventoAcceso::first()->user_id);
        $this->assertSame(0, Checkin::count());
    }

    public function test_alerta_del_agente_responde_200(): void
    {
        $this->enviar('/api/acceso/alerta', [
            'tipo'    => 'id_retrocedido',
            'detalle' => 'El id de Smart Pass es menor que el cursor.',
            'cuando'  => CarbonImmutable::now()->toIso8601String(),
        ], self::SECRETO)->assertOk();
    }

    public function test_alerta_sin_firma_rechaza(): void
    {
        $this->enviar('/api/acceso/alerta', [
            'tipo'    => 'id_retrocedido',
            'detalle' => 'x',
        ], secreto: null)->assertStatus(401);
    }
}
