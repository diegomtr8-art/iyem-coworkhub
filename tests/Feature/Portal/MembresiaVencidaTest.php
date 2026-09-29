<?php

namespace Tests\Feature\Portal;

use App\Enums\BolsaDeHoras;
use App\Enums\MotivoMovimiento;
use App\Models\Espacio;
use App\Models\MovimientoHoras;
use App\Models\Plane;
use App\Models\SolicitudAsesoria;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Asesorias\GestorDeAsesorias;
use App\Servicios\Horas\AjusteManual;
use App\Servicios\Horas\LibroDeHoras;
use App\Servicios\Reservas\ReservaOperativa;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Revisión del 29-sep-2026 — las horas de una membresía vencida.
 *
 * El estatus «Activa» no cambia al pasar la fecha de fin (solo al contratar
 * otra), y recepción reservaba, confirmaba asesorías y ajustaba horas sobre
 * membresías vencidas. El libro guardaba esos movimientos en un ciclo
 * posterior a la fecha de fin, y el control de saldos daba falsas alarmas que
 * detenían el despliegue.
 */
class MembresiaVencidaTest extends TestCase
{
    use RefreshDatabase;

    private function diaHabil(int $sumarDias = 0): string
    {
        return today()->next(Carbon::MONDAY)->addDays($sumarDias)->toDateString();
    }

    private function membresia(string $inicio, string $fin): Suscripcion
    {
        $plan = Plane::factory()->nodoPro()->create(['horas_asesoria_mes' => 4, 'max_horas_asesoria_dia' => 1]);

        return Suscripcion::factory()->delPlan($plan)->create([
            'user_id'      => User::factory()->miembro()->create()->id,
            'fecha_inicio' => $inicio,
            'fecha_fin'    => $fin,
        ])->load('plan', 'user');
    }

    // ── La regla ────────────────────────────────────────────────────────────

    public function test_la_membresia_cubre_hasta_su_ultimo_dia_pagado(): void
    {
        $s = $this->membresia(today()->subMonth()->toDateString(), today()->toDateString());

        $this->assertTrue($s->cubreElDia(today()->toDateString()));
        $this->assertFalse($s->cubreElDia(today()->addDay()->toDateString()));
        $this->assertFalse($s->vencida(), 'Hoy es su último día: todavía no venció.');

        $vencida = $this->membresia(today()->subMonths(2)->toDateString(), today()->subDays(10)->toDateString());
        $this->assertTrue($vencida->vencida());
        $this->assertSame(today()->subDays(10)->toDateString(), $vencida->diaDeReferencia()->toDateString());
    }

    // ── 1 · Recepción no descuenta horas fuera de la vigencia ───────────────

    public function test_recepcion_no_reserva_despues_del_fin_de_la_membresia(): void
    {
        $s = $this->membresia(today()->subMonth()->toDateString(), today()->toDateString());
        $sala = Espacio::factory()->salaJuntas()->create();

        try {
            app(ReservaOperativa::class)->crear($s->user, $sala, $this->diaHabil(), '10:00', '11:00', User::factory()->staff()->create());
            $this->fail('Debió rechazarse.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('fecha', $e->errors());
        }

        $this->assertSame(0, MovimientoHoras::where('suscripcion_id', $s->id)->where('motivo', MotivoMovimiento::Reserva->value)->count());
    }

    public function test_recepcion_si_reserva_dentro_de_la_vigencia(): void
    {
        $s = $this->membresia(today()->subDays(5)->toDateString(), today()->addMonth()->toDateString());
        $sala = Espacio::factory()->salaJuntas()->create();

        $resultado = app(ReservaOperativa::class)->crear($s->user, $sala, $this->diaHabil(), '10:00', '11:00', User::factory()->staff()->create());

        $this->assertNotNull($resultado['reserva']->id);
    }

    public function test_no_se_confirma_una_asesoria_despues_del_fin_de_la_membresia(): void
    {
        $s = $this->membresia(today()->subMonth()->toDateString(), today()->addDay()->toDateString());
        $solicitud = SolicitudAsesoria::create([
            'user_id' => $s->user_id, 'suscripcion_id' => $s->id, 'tema' => 'Precios',
            'dia_preferido' => today()->addDay(), 'horario_preferido' => 'Mañana', 'horas' => 1,
        ]);

        try {
            app(GestorDeAsesorias::class)->confirmar(
                $solicitud, User::factory()->staff()->create(),
                today()->addDays(5)->setTime(10, 0)->toDateTimeString(), asesorNombre: 'Lic. Ramírez',
            );
            $this->fail('Debió rechazarse.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('fecha_confirmada', $e->errors());
        }

        $this->assertSame(0.0, (float) $s->refresh()->horas_asesoria_usadas);
    }

    public function test_no_se_ajustan_horas_de_una_membresia_vencida(): void
    {
        $s = $this->membresia(today()->subMonths(2)->toDateString(), today()->subDays(10)->toDateString());

        try {
            app(AjusteManual::class)->aplicar($s, BolsaDeHoras::Sala, 5.5, 'Se cayó el internet', User::factory()->staff()->create());
            $this->fail('Debió rechazarse.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('horas', $e->errors());
        }

        $this->assertSame(0, MovimientoHoras::where('suscripcion_id', $s->id)->where('motivo', MotivoMovimiento::AjusteManual->value)->count());
    }

    // ── 2 · El control de saldos mide la vencida en su último ciclo ─────────

    public function test_una_membresia_vencida_no_da_falsa_alarma_en_el_control_de_saldos(): void
    {
        // Contrató hace dos meses y venció hace diez días; en su último ciclo
        // usó 3 horas de sala, registradas en el libro y en el contador.
        $inicio = today()->subMonths(2)->subDays(5);
        $s = $this->membresia($inicio->toDateString(), today()->subDays(10)->toDateString());
        $enSuUltimoCiclo = CarbonImmutable::parse($s->fecha_fin->toDateString());

        app(LibroDeHoras::class)->registrar(
            suscripcion: $s, bolsa: BolsaDeHoras::Sala, cantidad: 3,
            motivo: MotivoMovimiento::AjusteManual, nota: 'Consumo de su último ciclo (prueba).', en: $enSuUltimoCiclo,
        );
        $this->assertSame(3.0, (float) $s->refresh()->horas_sala_usadas);

        $this->assertSame([], app(LibroDeHoras::class)->verificarConsistencia($s->refresh()));
        $this->assertSame(0, Artisan::call('nodico:reconstruir-saldos'), 'Sin divergencias: no detiene el despliegue.');

        // Y «arreglar» no le borra el consumo de su último ciclo.
        Artisan::call('nodico:reconstruir-saldos', ['--arreglar' => true]);
        $this->assertSame(3.0, (float) $s->refresh()->horas_sala_usadas);
    }
}
