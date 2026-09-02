<?php

namespace Tests\Feature\Tablero;

use App\Models\BloqueoEspacio;
use App\Models\Checkin;
use App\Models\Espacio;
use App\Models\Reserva;
use App\Models\User;
use App\Servicios\Tablero\EstadoDeEspacios;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 2 — el motor de estados del tablero. Reloj fijo (miércoles 10:00) para
 * que las pruebas no dependan de la hora real.
 */
class EstadoDeEspaciosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 9, 2, 10, 0, 0)); // miércoles
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function motor(): EstadoDeEspacios
    {
        return app(EstadoDeEspacios::class);
    }

    private function sala(string $tipo = 'sala_juntas', int $cap = 8): Espacio
    {
        return Espacio::factory()->create(['tipo' => $tipo, 'capacidad' => $cap, 'disponible' => true]);
    }

    private function estadoDe(int $espacioId): ?array
    {
        foreach ($this->motor()->paraTablero() as $e) {
            if ($e['id'] === $espacioId) {
                return $e;
            }
        }

        return null;
    }

    public function test_una_reserva_corriendo_marca_ocupada_con_su_hora_de_fin(): void
    {
        $sala = $this->sala();
        Reserva::factory()->create(['espacio_id' => $sala->id, 'fecha' => today(),
            'hora_inicio' => '09:00:00', 'hora_fin' => '11:00:00', 'estatus' => 'Confirmada']);

        $e = $this->estadoDe($sala->id);
        $this->assertSame('ocupada', $e['estado']);
        $this->assertSame('11:00', $e['hasta']);
    }

    public function test_terminada_la_reserva_queda_libre(): void
    {
        $sala = $this->sala();
        Reserva::factory()->create(['espacio_id' => $sala->id, 'fecha' => today(),
            'hora_inicio' => '09:00:00', 'hora_fin' => '11:00:00', 'estatus' => 'Confirmada']);

        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 9, 2, 12, 0, 0));
        $this->assertSame('libre', $this->estadoDe($sala->id)['estado']);
    }

    public function test_aparta_pronto_cuando_falta_menos_de_media_hora(): void
    {
        $sala = $this->sala();
        Reserva::factory()->create(['espacio_id' => $sala->id, 'fecha' => today(),
            'hora_inicio' => '10:20:00', 'hora_fin' => '12:00:00', 'estatus' => 'Confirmada']);

        $e = $this->estadoDe($sala->id);
        $this->assertSame('aparta_pronto', $e['estado']);
        $this->assertSame('10:20', $e['desde']);
    }

    public function test_un_bloqueo_gana_sobre_una_reserva(): void
    {
        $sala = $this->sala();
        Reserva::factory()->create(['espacio_id' => $sala->id, 'fecha' => today(),
            'hora_inicio' => '09:00:00', 'hora_fin' => '11:00:00', 'estatus' => 'Confirmada']);
        BloqueoEspacio::create(['espacio_id' => $sala->id, 'fecha' => today(),
            'hora_inicio' => '09:00:00', 'hora_fin' => '13:00:00', 'motivo' => 'Mantenimiento']);

        $this->assertSame('bloqueada', $this->estadoDe($sala->id)['estado']);
    }

    public function test_fuera_de_horario_lo_dice(): void
    {
        $sala = $this->sala();
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 9, 2, 21, 0, 0)); // cerrado
        $this->assertSame('fuera_horario', $this->estadoDe($sala->id)['estado']);
    }

    public function test_un_checkin_colgado_no_infla_el_porcentaje(): void
    {
        $cowork = $this->sala('coworking', 70);
        $u1 = User::factory()->miembro()->create();
        $u2 = User::factory()->miembro()->create();

        // Uno válido (entró hace una hora) y uno colgado (hace dos días).
        Checkin::create(['user_id' => $u1->id, 'espacio_id' => $cowork->id, 'hora_entrada' => now()->subHour()]);
        Checkin::create(['user_id' => $u2->id, 'espacio_id' => $cowork->id, 'hora_entrada' => now()->subDays(2)]);

        $e = $this->estadoDe($cowork->id);
        $this->assertSame('coworking', $e['estado']);
        $this->assertSame(1, $e['personas_dentro']);   // el colgado no cuenta
    }

    public function test_los_salones_de_eventos_no_aparecen_en_el_tablero(): void
    {
        $salon = $this->sala('salon_eventos', 120);
        $this->assertNull($this->estadoDe($salon->id));
    }
}
