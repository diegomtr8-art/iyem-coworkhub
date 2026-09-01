<?php

namespace Tests\Feature\Acceso;

use App\Enums\AccionOperativa;
use App\Models\ComandoAcceso;
use App\Models\PersonaAcceso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Fase 4/5 (parte 2) — los tres listados de personas, el amarre de FaceID único,
 * y la cola de «abrir puerta» de Nódico hacia el agente.
 */
class PanelPersonasYPuertaTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETO = 'clave-de-prueba-del-agente';

    protected function setUp(): void
    {
        parent::setUp();
        config(['acceso.agente_secreto' => self::SECRETO, 'acceso.replay_ventana_segundos' => 300]);
    }

    // ── Listados y CRUD ───────────────────────────────────────────────────────

    public function test_solo_el_mostrador_ve_los_listados(): void
    {
        $this->actingAs(User::factory()->miembro()->create())
            ->get(route('personas.index'))->assertForbidden();

        $this->actingAs(User::factory()->staff()->create())
            ->get(route('personas.index'))->assertOk();
    }

    public function test_alta_edicion_y_baja_de_una_ficha(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('personas.store'), [
            'categoria' => 'servicio_social',
            'nombre'    => 'Ana Prestadora',
            'inicio'    => '2026-09-01',
            'fin'       => '2027-03-01',
            'activo'    => true,
        ])->assertRedirect();

        $ficha = PersonaAcceso::firstWhere('nombre', 'Ana Prestadora');
        $this->assertNotNull($ficha);
        $this->assertSame('servicio_social', $ficha->categoria->value);

        $this->actingAs($staff)->patch(route('personas.update', $ficha), [
            'categoria' => 'servicio_social', 'nombre' => 'Ana P.', 'activo' => false,
        ])->assertRedirect();
        $this->assertFalse($ficha->refresh()->activo);

        $this->actingAs($staff)->delete(route('personas.destroy', $ficha))->assertRedirect();
        $this->assertModelMissing($ficha);
    }

    public function test_fin_anterior_al_inicio_se_rechaza(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->post(route('personas.store'), [
                'categoria' => 'servicio_social', 'nombre' => 'X',
                'inicio' => '2026-09-10', 'fin' => '2026-09-01',
            ])->assertSessionHasErrors('fin');
    }

    // ── FaceID único ──────────────────────────────────────────────────────────

    public function test_vincular_y_que_el_rostro_es_unico_en_todo_el_sistema(): void
    {
        $staff   = User::factory()->staff()->create();
        $miembro = User::factory()->miembro()->create();
        $ficha   = PersonaAcceso::create(['categoria' => 'empleado', 'nombre' => 'Beto']);

        // Vincular el rostro 99 a un empleado.
        $this->actingAs($staff)->post(route('personas.vincular'), [
            'tipo' => 'persona', 'id' => $ficha->id, 'person_id' => 99,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(99, $ficha->refresh()->smartpass_person_id);

        // Ese mismo rostro no puede ir a un miembro.
        $this->actingAs($staff)->post(route('personas.vincular'), [
            'tipo' => 'miembro', 'id' => $miembro->id, 'person_id' => 99,
        ])->assertSessionHas('error');
        $this->assertNull($miembro->refresh()->smartpass_person_id);

        // Desvincular libera el rostro.
        $this->actingAs($staff)->post(route('personas.desvincular'), [
            'tipo' => 'persona', 'id' => $ficha->id,
        ])->assertRedirect();
        $this->assertNull($ficha->refresh()->smartpass_person_id);

        $this->assertDatabaseHas('bitacora_operacion', [
            'accion' => AccionOperativa::VinculacionRostro->value,
        ]);
    }

    // ── Puerta: cola de comandos ───────────────────────────────────────────────

    public function test_abrir_puerta_encola_y_queda_en_bitacora(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->post(route('accesos.abrir'), ['device_id' => 7])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('comandos_acceso', [
            'tipo' => 'abrir_puerta', 'device_id' => 7, 'estado' => 'pendiente',
            'solicitado_por_user_id' => $staff->id,
        ]);
        $this->assertDatabaseHas('bitacora_operacion', [
            'accion' => AccionOperativa::AperturaPuerta->value,
        ]);
    }

    public function test_el_miembro_no_puede_abrir_la_puerta(): void
    {
        $this->actingAs(User::factory()->miembro()->create())
            ->post(route('accesos.abrir'), ['device_id' => 7])->assertForbidden();
    }

    public function test_el_agente_recoge_el_comando_y_reporta_el_resultado(): void
    {
        $comando = ComandoAcceso::create(['tipo' => 'abrir_puerta', 'device_id' => 7, 'estado' => 'pendiente']);

        // El agente (firmado) recoge pendientes → se marcan «enviado».
        $r = $this->firmado('/api/acceso/comandos', ['agente' => now()->toIso8601String()]);
        $r->assertOk()->assertJsonPath('comandos.0.id', $comando->id);
        $this->assertSame('enviado', $comando->refresh()->estado);

        // Y reporta que abrió → «ejecutado».
        $this->firmado("/api/acceso/comandos/{$comando->id}/resultado", ['ok' => true, 'detalle' => 'code 200'])
            ->assertOk();
        $this->assertSame('ejecutado', $comando->refresh()->estado);
        $this->assertSame('code 200', $comando->resultado);
    }

    public function test_sin_firma_no_se_entregan_comandos(): void
    {
        ComandoAcceso::create(['tipo' => 'abrir_puerta', 'device_id' => 7, 'estado' => 'pendiente']);
        $this->postJson('/api/acceso/comandos', [])->assertStatus(401);
    }

    public function test_enrolar_encola_y_al_reportar_el_person_id_queda_vinculado(): void
    {
        $staff = User::factory()->staff()->create();
        $ficha = PersonaAcceso::create(['categoria' => 'empleado', 'nombre' => 'Carla', 'identificador' => 'EMP7']);

        // El mostrador pide enrolar con una foto.
        $this->actingAs($staff)->post(route('personas.enrolar'), [
            'tipo' => 'persona', 'id' => $ficha->id, 'modo' => 'foto', 'foto_base64' => 'QUJD',
        ])->assertRedirect()->assertSessionHas('success');

        $comando = ComandoAcceso::where('tipo', 'enrolar_rostro')->firstOrFail();
        $this->assertSame('persona', $comando->payload['perfil_tipo']);
        $this->assertSame('EMP7', $comando->payload['person_no']);

        // El agente lo recoge…
        $this->firmado('/api/acceso/comandos', ['agente' => now()->toIso8601String()])->assertOk();

        // …y reporta el person_id que Smart Pass devolvió → se vincula solo.
        $this->firmado("/api/acceso/comandos/{$comando->id}/resultado", ['ok' => true, 'person_id' => 555])
            ->assertOk();

        $this->assertSame(555, $ficha->refresh()->smartpass_person_id);
        $this->assertSame('ejecutado', $comando->refresh()->estado);
    }

    /** Firma un cuerpo como lo haría el agente y lo envía. */
    private function firmado(string $uri, array $payload): TestResponse
    {
        $cuerpo = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ts = (string) time();
        $headers = [
            'Content-Type'       => 'application/json',
            'Accept'             => 'application/json',
            'X-Agente-Timestamp' => $ts,
            'X-Agente-Firma'     => hash_hmac('sha256', $ts . '.' . $cuerpo, self::SECRETO),
        ];

        return $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers), $cuerpo);
    }
}
