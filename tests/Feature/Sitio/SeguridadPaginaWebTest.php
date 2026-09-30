<?php

namespace Tests\Feature\Sitio;

use App\Enums\AccionOperativa;
use App\Models\EntradaBitacora;
use App\Models\User;
use App\Servicios\Sitio\ComprobadorDeEnlaces;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Fase 5 del módulo «Página Web»: bitácora de cada cambio y aviso de enlaces
 * que no responden. Permisos, HTML y respaldo ante basura ya los cubren
 * PanelPaginaWebTest y ContenidoDelSitioTest.
 */
class SeguridadPaginaWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Coordinación']);

        // Sin DNS de verdad en las pruebas: los hosts de ejemplo resuelven a una
        // IP pública fija, y `interno.example` a una privada.
        $this->app->bind(ComprobadorDeEnlaces::class, fn () => new class extends ComprobadorDeEnlaces {
            protected function ipPublica(string $host): ?string
            {
                return match (true) {
                    str_starts_with($host, '127.') || $host === 'interno.example' => null,
                    default => '93.184.216.34',
                };
            }
        });
    }

    private function aliados(array $cambios = []): array
    {
        return $cambios + [
            'visible'         => true,
            'etiqueta'        => 'Con el respaldo de',
            'iyem_nombre'     => 'Instituto Yucateco de Emprendedores',
            'iyem_url'        => 'https://iyem.yucatan.gob.mx',
            'herencia_nombre' => 'Herencia Viva',
            'herencia_url'    => 'https://www.herenciaviva.com',
            'canieti_nombre'  => 'CANIETI',
            'canieti_url'     => null,
        ];
    }

    // --- Bitácora ---------------------------------------------------------------

    public function test_cada_cambio_queda_en_la_bitacora_con_quien_y_el_antes_y_el_despues(): void
    {
        Http::fake();

        $this->actingAs($this->admin)
            ->put(route('pagina-web.guardar', 'comun.aliados'), $this->aliados(['herencia_nombre' => 'Tienda Herencia Viva']))
            ->assertSessionHasNoErrors();

        $entrada = EntradaBitacora::sole();

        $this->assertSame(AccionOperativa::EdicionSitio->value, $entrada->accion);
        $this->assertSame($this->admin->id, $entrada->actor_user_id);
        $this->assertSame('Todas las páginas · «Aliados»: herencia viva · nombre', $entrada->descripcion);
        $this->assertSame(['antes' => 'Herencia Viva', 'despues' => 'Tienda Herencia Viva'], $entrada->contexto['cambios']['herencia_nombre']);
        $this->assertSame(['herencia_nombre'], array_keys($entrada->contexto['cambios']));
        $this->assertSame('comun.aliados', $entrada->contexto['seccion']);
    }

    public function test_deshacer_y_volver_al_original_tambien_se_registran(): void
    {
        Http::fake();
        $this->actingAs($this->admin);

        $this->put(route('pagina-web.guardar', 'comun.aliados'), $this->aliados(['etiqueta' => 'Nos respaldan']));
        $this->post(route('pagina-web.deshacer', 'comun.aliados'));
        $this->put(route('pagina-web.guardar', 'comun.aliados'), $this->aliados(['etiqueta' => 'Nos respaldan']));
        $this->post(route('pagina-web.restablecer', 'comun.aliados'));

        $this->assertSame(
            ['edicion_sitio', 'deshacer_sitio', 'edicion_sitio', 'restablecer_sitio'],
            EntradaBitacora::orderBy('id')->pluck('accion')->all(),
        );

        $deshacer = EntradaBitacora::where('accion', 'deshacer_sitio')->sole();
        $this->assertSame(['antes' => 'Nos respaldan', 'despues' => 'Con el respaldo de'], $deshacer->contexto['cambios']['etiqueta']);
    }

    public function test_guardar_sin_cambios_no_llena_la_bitacora(): void
    {
        $this->actingAs($this->admin)
            ->put(route('pagina-web.guardar', 'comun.aliados'), $this->aliados())
            ->assertSessionHas('info');

        $this->assertSame(0, EntradaBitacora::count());
    }

    public function test_un_intento_rechazado_no_se_registra_como_cambio(): void
    {
        $this->actingAs($this->admin)
            ->put(route('pagina-web.guardar', 'comun.aliados'), $this->aliados(['iyem_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('iyem_url');

        $this->assertSame(0, EntradaBitacora::count());
    }

    public function test_las_listas_largas_se_resumen_en_la_bitacora(): void
    {
        Http::fake();
        $elementos = array_map(fn ($n) => ['titulo' => "Valor {$n}", 'descripcion' => str_repeat('texto ', 25)], range(1, 6));

        $this->actingAs($this->admin)
            ->put(route('pagina-web.guardar', 'nosotros.valores'), ['visible' => true, 'titulo' => 'Lo que nos mueve', 'elementos' => $elementos]);

        $despues = EntradaBitacora::sole()->contexto['cambios']['elementos']['despues'];
        $this->assertLessThanOrEqual(500, mb_strlen($despues));
    }

    // --- Enlaces ----------------------------------------------------------------

    public function test_un_enlace_nuevo_que_no_responde_se_guarda_pero_avisa(): void
    {
        Http::fake(['www.herenciaviva.com/*' => Http::response('', 404)]);

        $this->actingAs($this->admin)
            ->put(route('pagina-web.guardar', 'comun.aliados'), $this->aliados(['herencia_url' => 'https://www.herenciaviva.com/no-existe']))
            ->assertSessionHas('warning', fn ($m) => str_contains($m, '«Herencia Viva · enlace»'));

        $this->assertSame('https://www.herenciaviva.com/no-existe', app(\App\Servicios\Sitio\ContenidoDelSitio::class)->valor('comun.aliados', 'herencia_url'));
    }

    public function test_un_enlace_que_responde_o_redirige_no_avisa(): void
    {
        Http::fake(['maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps'])]);

        $this->actingAs($this->admin)
            ->put(route('pagina-web.guardar', 'contacto'), ['maps_url' => 'https://maps.app.goo.gl/OtroLugar'])
            ->assertSessionHas('success');
    }

    public function test_solo_se_comprueban_los_enlaces_que_cambiaron(): void
    {
        Http::fake();

        $this->actingAs($this->admin)
            ->put(route('pagina-web.guardar', 'comun.aliados'), $this->aliados(['etiqueta' => 'Nos respaldan']));

        Http::assertNothingSent();
    }

    public function test_nunca_se_consulta_una_direccion_interna(): void
    {
        Http::fake();
        $comprobador = app(ComprobadorDeEnlaces::class);

        $this->assertFalse($comprobador->responde('https://127.0.0.1/admin'));
        $this->assertFalse($comprobador->responde('https://interno.example/'));
        $this->assertFalse($comprobador->responde('http://www.herenciaviva.com/'));

        Http::assertNothingSent();
    }

    public function test_un_sitio_caido_cuenta_como_que_no_responde(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

        $this->assertFalse(app(ComprobadorDeEnlaces::class)->responde('https://www.herenciaviva.com/'));
    }
}
