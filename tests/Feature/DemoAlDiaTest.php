<?php

namespace Tests\Feature;

use App\Models\Espacio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Pruebas de servicio social (29-sep-2026) — el problema de datos: el demo se
 * sembró una vez con fechas relativas a ese día y nadie lo volvió a sembrar,
 * así que un mes después todas sus membresías habían vencido.
 * `nodico:demo-al-dia` lo resiembra cuando envejece.
 */
class DemoAlDiaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Planes y espacios base (DatabaseSeeder llama a NodicoWebSeeder),
        // como en el servidor de pruebas.
        $this->seed();
    }

    private function idDelAdminDemo(): ?int
    {
        return User::where('email', 'admin.demo@nodico.com.mx')->value('id');
    }

    public function test_sin_demo_lo_siembra(): void
    {
        $this->artisan('nodico:demo-al-dia')->assertSuccessful();

        $this->assertNotNull($this->idDelAdminDemo());
    }

    public function test_un_demo_reciente_no_se_toca(): void
    {
        Artisan::call('nodico:demo-al-dia');
        $antes = $this->idDelAdminDemo();

        $this->travel(3)->days();
        $this->artisan('nodico:demo-al-dia')->assertSuccessful();

        $this->assertSame($antes, $this->idDelAdminDemo(), 'No borra lo que la gente está probando.');
    }

    /**
     * El síntoma de las pruebas, de punta a punta: un mes después de sembrar,
     * `pro.demo` no tenía días que reservar; tras resembrar, sí.
     */
    public function test_un_demo_viejo_se_resiembra_y_pro_demo_vuelve_a_poder_reservar(): void
    {
        Artisan::call('nodico:demo-al-dia');
        $antes = $this->idDelAdminDemo();

        $this->travel(29)->days();
        $pro = User::where('email', 'pro.demo@nodico.com.mx')->firstOrFail();
        $sala = Espacio::where('tipo', 'sala_juntas')->firstOrFail();
        $this->actingAs($pro)
            ->getJson(route('portal.disponibilidad', ['espacio_id' => $sala->id, 'fecha' => today()->toDateString(), 'mes' => 1]))
            ->assertStatus(409); // vencida: ya lo dice, pero no puede reservar.

        $this->artisan('nodico:demo-al-dia')->assertSuccessful();

        $this->assertNotSame($antes, $this->idDelAdminDemo());
        $pro = User::where('email', 'pro.demo@nodico.com.mx')->firstOrFail();
        $this->app['auth']->forgetGuards();
        $dias = $this->actingAs($pro)
            ->getJson(route('portal.disponibilidad', ['espacio_id' => $sala->id, 'fecha' => today()->toDateString(), 'mes' => 1]))
            ->assertOk()
            ->json('dias');

        $this->assertNotEmpty($dias, 'Tras resembrar, pro.demo vuelve a tener días en el calendario.');
    }

    /**
     * Regresión del servidor de pruebas (29-sep-2026): `admin.demo` confirmó
     * una orden de pago y `ordenes_pago.confirmada_por_user_id` (RESTRICT)
     * impidió borrarlo; el despliegue se detuvo. La orden tiene que sobrevivir.
     */
    public function test_resiembra_aunque_una_cuenta_demo_haya_confirmado_una_orden(): void
    {
        Artisan::call('nodico:demo-al-dia');
        $admin = User::where('email', 'admin.demo@nodico.com.mx')->firstOrFail();
        $miembro = User::factory()->miembro()->create();
        $plan = \App\Models\Plane::firstOrFail();
        $orden = \App\Models\OrdenPago::create([
            'user_id' => $miembro->id, 'plan_id' => $plan->id, 'referencia' => 'NDC-PRUEBA', 'referencia_normalizada' => 'NDCPRUEBA', 'vence_el' => now()->addWeek(),
            'monto' => $plan->precio, 'metodo' => 'efectivo', 'estado_pago' => 'confirmada',
            'confirmada_por_user_id' => $admin->id, 'confirmada_en' => now(),
        ]);

        $this->artisan('nodico:demo-al-dia', ['--forzar' => true])->assertSuccessful();

        $this->assertNotSame($admin->id, $this->idDelAdminDemo(), 'El demo se rehízo.');
        $this->assertNull($orden->refresh()->confirmada_por_user_id);
        $this->assertSame($miembro->id, $orden->user_id, 'La orden del miembro sigue ahí.');
    }

    public function test_no_corre_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('nodico:demo-al-dia')->assertFailed();
        $this->assertNull($this->idDelAdminDemo());
    }
}
