<?php

namespace Tests\Unit;

use App\Models\Reserva;
use App\Servicios\Reservas\RegistroDeBloques;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * `Reserva::calcularHoras()` es el método más pequeño del sistema y el que
 * corrompió todos los saldos. Se prueba aparte y sin tocar la base de datos.
 *
 * Extiende `Tests\TestCase` y no el de PHPUnit porque `RegistroDeBloques` lee la
 * granularidad de `config()`: los 30 minutos del índice único y los de la
 * configuración tienen que ser el mismo número, no dos constantes paralelas.
 */
class CalculoDeHorasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Estos tests prueban el algoritmo de mapeo hora→bloque con una
        // granularidad de referencia conocida (30 min), no la regla de negocio
        // (hoy 60 min): así siguen verificando el índice único y el traslape a
        // media hora sin depender del default de la configuración.
        config(['nodico.operacion.granularidad_minutos' => 30]);
    }

    public static function duraciones(): array
    {
        return [
            'dos horas exactas'       => ['09:00', '11:00', 2.0],
            'una hora'                => ['09:00', '10:00', 1.0],
            'media hora'              => ['09:00', '09:30', 0.5],
            'hora y media'            => ['09:00', '10:30', 1.5],
            'jornada completa'        => ['09:00', '19:00', 10.0],
            'con segundos de la base' => ['09:00:00', '11:00:00', 2.0],
            'formatos mezclados'      => ['09:00', '11:00:00', 2.0],
            'cruzando el mediodía'    => ['11:30', '13:00', 1.5],
        ];
    }

    /**
     * @dataProvider duraciones
     */
    public function test_la_duracion_siempre_es_positiva(string $inicio, string $fin, float $esperado): void
    {
        $horas = Reserva::calcularHoras($inicio, $fin);

        $this->assertSame($esperado, $horas);
        $this->assertGreaterThan(0, $horas, 'Una duración negativa es el BUG-01 de vuelta.');
    }

    /**
     * La comprobación que demuestra el porqué del método: así es como se
     * calculaba antes, y esto es lo que devolvía.
     */
    public function test_carbon_3_devuelve_la_diferencia_con_signo(): void
    {
        $inicio = Carbon::createFromFormat('H:i', '09:00');
        $fin    = Carbon::createFromFormat('H:i', '11:00');

        $this->assertSame(
            -120.0,
            $fin->diffInMinutes($inicio),
            'Si esto deja de ser negativo, Carbon cambió de comportamiento y el comentario de '
            . 'Reserva::calcularHoras() hay que revisarlo.'
        );

        // Y en el orden correcto:
        $this->assertSame(120.0, $inicio->diffInMinutes($fin));
    }

    public function test_un_rango_invertido_revienta_en_vez_de_devolver_un_negativo(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Reserva::calcularHoras('11:00', '09:00');
    }

    public function test_un_rango_de_duracion_cero_tambien_revienta(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Reserva::calcularHoras('09:00', '09:00');
    }

    public function test_una_hora_con_formato_raro_revienta(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Reserva::calcularHoras('9am', '11:00');
    }

    public function test_una_hora_fuera_de_rango_revienta(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Reserva::calcularHoras('09:00', '25:00');
    }

    // ── Bloques de 30 minutos ───────────────────────────────────────────────

    public function test_los_bloques_cubren_el_rango_con_el_fin_exclusivo(): void
    {
        $registro = new RegistroDeBloques();

        // 09:00–10:00 ocupa 09:00 y 09:30, no 10:00.
        $this->assertSame([18, 19], $registro->bloquesDe('09:00', '10:00'));

        // Y la siguiente encaja justo detrás sin pisarla.
        $this->assertSame([20, 21], $registro->bloquesDe('10:00', '11:00'));
    }

    public function test_dos_rangos_que_se_pisan_comparten_al_menos_un_bloque(): void
    {
        $registro = new RegistroDeBloques();

        $primera = $registro->bloquesDe('09:00', '10:00');
        $segunda = $registro->bloquesDe('09:30', '10:30');

        $this->assertNotEmpty(
            array_intersect($primera, $segunda),
            'Si no comparten bloque, el índice único no detecta el traslape.'
        );
    }

    public function test_una_media_hora_ocupa_un_solo_bloque(): void
    {
        $this->assertSame([18], (new RegistroDeBloques())->bloquesDe('09:00', '09:30'));
    }
}
