<?php

namespace Tests\Feature\Tablero;

use App\Models\Espacio;
use App\Models\Reserva;
use App\Models\TableroEnlace;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 6/7 — el endpoint público del tablero. Lo que más importa: que el JSON
 * NO lleve nombres ni datos de personas, y que el token revocado no funcione.
 */
class TableroPublicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 9, 2, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_el_json_publico_no_contiene_nombres_ni_datos_de_personas(): void
    {
        $enlace = TableroEnlace::generar('Tele');
        $ana = User::factory()->miembro()->create(['name' => 'Ana Ejemplo Secreta', 'email' => 'ana.secreta@ejemplo.mx']);
        $sala = Espacio::factory()->create(['tipo' => 'sala_juntas', 'capacidad' => 8, 'disponible' => true]);

        // Reserva de Ana corriendo ahora: la sala debe salir ocupada, pero Ana NO.
        Reserva::factory()->create([
            'user_id' => $ana->id, 'espacio_id' => $sala->id, 'fecha' => today(),
            'hora_inicio' => '09:00:00', 'hora_fin' => '11:00:00', 'estatus' => 'Confirmada',
        ]);

        $cuerpo = $this->get("/tablero/{$enlace->token}/datos")->assertOk()->getContent();

        $this->assertStringNotContainsString('Ana Ejemplo Secreta', $cuerpo);
        $this->assertStringNotContainsString('ana.secreta@ejemplo.mx', $cuerpo);
        $this->assertStringNotContainsString('user_id', $cuerpo);
        $this->assertStringContainsString('ocupada', $cuerpo);   // el estado del espacio sí viaja
    }

    public function test_la_agenda_de_un_espacio_tampoco_trae_nombres(): void
    {
        $enlace = TableroEnlace::generar('Tele');
        $beto = User::factory()->miembro()->create(['name' => 'Beto Nombre Privado']);
        $sala = Espacio::factory()->create(['tipo' => 'sala_juntas', 'capacidad' => 8, 'disponible' => true]);
        Reserva::factory()->create([
            'user_id' => $beto->id, 'espacio_id' => $sala->id, 'fecha' => today(),
            'hora_inicio' => '14:00:00', 'hora_fin' => '16:00:00', 'estatus' => 'Confirmada',
        ]);

        $cuerpo = $this->get("/tablero/{$enlace->token}/espacio/{$sala->id}/agenda")->assertOk()->getContent();

        $this->assertStringNotContainsString('Beto Nombre Privado', $cuerpo);
        $this->assertStringContainsString('14:00', $cuerpo);   // el horario sí
    }

    public function test_un_token_revocado_deja_de_funcionar_de_inmediato(): void
    {
        $enlace = TableroEnlace::generar('Tele');
        $this->get("/tablero/{$enlace->token}/datos")->assertOk();

        $enlace->forceFill(['revocado_en' => now()])->save();
        $this->get("/tablero/{$enlace->token}/datos")->assertNotFound();
        $this->get("/tablero/{$enlace->token}")->assertNotFound();
    }

    public function test_un_token_inventado_no_existe(): void
    {
        $this->get('/tablero/' . str_repeat('x', 48) . '/datos')->assertNotFound();
    }
}
