<?php

namespace Tests\Feature\ApiMovil;

use App\Console\Commands\TokenIntegracion;
use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * `nodico:token-integracion`: la cuenta de servicio del IYEM ERP.
 */
class TokenIntegracionTest extends TestCase
{
    use ApiMovil, RefreshDatabase;

    private function emitir(array $opciones = []): string
    {
        Artisan::call('nodico:token-integracion', $opciones);

        // El token es la única línea con la forma `id|secreto`.
        // Sin `$`: en Windows la línea termina en \r\n.
        preg_match('/^\d+\|\S+/m', Artisan::output(), $coincidencia);

        return $coincidencia[0] ?? '';
    }

    public function test_crea_la_cuenta_de_servicio_y_su_token_abre_los_reportes(): void
    {
        $token = $this->emitir();

        $this->assertNotSame('', $token);

        $cuenta = User::where('email', 'erp@iyemyucatan.com')->firstOrFail();
        $this->assertSame(RolUsuario::Admin, $cuenta->rol);
        $this->assertStringContainsString(TokenIntegracion::MARCA, $cuenta->notas_admin);

        // Sin `X-App-Version`, como llama el ERP.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/reportes/resumen', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonStructure(['data' => ['rango', 'ocupacion_media_pct', 'ingresos', 'miembros_en_riesgo']]);

        // La cara de reportes no abre las rutas del miembro.
        $this->api('GET', 'reservas', token: $token)->assertStatus(403)->assertJsonPath('codigo', 'sin_permiso');
    }

    public function test_volver_a_emitir_revoca_el_token_anterior(): void
    {
        $viejo = $this->emitir();
        $nuevo = $this->emitir();

        $this->assertSame(1, User::where('email', 'erp@iyemyucatan.com')->first()->tokens()->count());
        $this->api('GET', 'reportes/resumen', token: $viejo)->assertStatus(401);
        $this->api('GET', 'reportes/resumen', token: $nuevo)->assertOk();
    }

    public function test_revocar_corta_la_integracion(): void
    {
        $token = $this->emitir();

        $this->emitir(['--revocar' => true]);

        $this->api('GET', 'reportes/resumen', token: $token)->assertStatus(401);
    }

    public function test_quitarle_el_rol_corta_el_token_en_la_siguiente_peticion(): void
    {
        $token = $this->emitir();

        User::where('email', 'erp@iyemyucatan.com')->first()->asignarRol(RolUsuario::Staff);

        $this->api('GET', 'reportes/resumen', token: $token)->assertStatus(403)->assertJsonPath('codigo', 'sin_permiso');
    }

    public function test_no_toca_la_cuenta_de_una_persona_con_ese_correo(): void
    {
        $persona = User::factory()->create(['email' => 'erp@iyemyucatan.com']);

        $this->assertSame(1, Artisan::call('nodico:token-integracion'));
        $this->assertSame(0, $persona->fresh()->tokens()->count());
        $this->assertNotSame(RolUsuario::Admin, $persona->fresh()->rol);
    }
}
