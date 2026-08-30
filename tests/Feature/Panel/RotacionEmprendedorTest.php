<?php

namespace Tests\Feature\Panel;

use App\Models\DirectorioEmprendedor;
use App\Models\User;
use App\Servicios\Emprendedores\RotacionDeEmprendedor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4.H — el emprendedor de la semana rota solo, de forma justa: nadie se
 * repite hasta que todos hayan pasado, y la sección nunca queda vacía.
 */
class RotacionEmprendedorTest extends TestCase
{
    use RefreshDatabase;

    private function crear(string $nombre, array $extra = []): DirectorioEmprendedor
    {
        return DirectorioEmprendedor::create(array_merge([
            'nombre' => $nombre, 'activo' => true, 'elegible_destacado' => true,
        ], $extra));
    }

    public function test_la_rotacion_no_repite_hasta_agotar_el_catalogo(): void
    {
        foreach (['A', 'B', 'C'] as $n) {
            $this->crear($n);
        }
        $rotacion = new RotacionDeEmprendedor();

        // Cuatro semanas seguidas: los tres salen antes de que ninguno repita.
        $salieron = [];
        for ($i = 0; $i < 4; $i++) {
            $r = $rotacion->rotar(CarbonImmutable::today()->addWeeks($i));
            $salieron[] = $r['destacado'];
        }

        // Las tres primeras semanas son los tres distintos; la cuarta reabre el ciclo.
        $this->assertEqualsCanonicalizing(['A', 'B', 'C'], array_slice($salieron, 0, 3));
        $this->assertSame($salieron[0], $salieron[3]); // el primero vuelve recién en la 4a
    }

    public function test_solo_uno_queda_destacado_a_la_vez(): void
    {
        $this->crear('A'); $this->crear('B');
        (new RotacionDeEmprendedor())->rotar();
        (new RotacionDeEmprendedor())->rotar();

        $this->assertSame(1, DirectorioEmprendedor::where('destacado_semana', true)->count());
    }

    public function test_sin_elegibles_mantiene_al_actual_y_avisa(): void
    {
        // Uno destacado pero ya no elegible (no debe rotar a nadie más).
        $a = $this->crear('A', ['destacado_semana' => true, 'elegible_destacado' => false]);

        $r = (new RotacionDeEmprendedor())->rotar();

        $this->assertSame('sin_elegibles', $r['estado']);
        $this->assertNotNull($r['aviso']);
        $this->assertTrue($a->refresh()->destacado_semana); // sigue destacado: no queda vacío
    }

    public function test_un_fijado_a_mano_se_respeta_y_no_rota(): void
    {
        $this->crear('A');
        $fijado = $this->crear('Especial');

        (new RotacionDeEmprendedor())->fijar($fijado);
        $r = (new RotacionDeEmprendedor())->rotar();

        $this->assertSame('fijado', $r['estado']);
        $this->assertSame('Especial', $r['destacado']);
        $this->assertTrue($fijado->refresh()->destacado_semana);
    }

    public function test_fijar_desde_el_panel_destaca_a_ese(): void
    {
        $this->crear('A', ['destacado_semana' => true]);
        $otro = $this->crear('Otro');

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('emprendedores.fijar', $otro->id))
            ->assertRedirect();

        $this->assertTrue($otro->refresh()->destacado_semana);
        $this->assertSame(1, DirectorioEmprendedor::where('destacado_semana', true)->count());
    }

    public function test_el_comando_de_rotacion_corre(): void
    {
        $this->crear('A');
        $this->artisan('nodico:rotar-emprendedor')->assertSuccessful();
        $this->assertSame('A', DirectorioEmprendedor::where('destacado_semana', true)->first()?->nombre);
    }
}
