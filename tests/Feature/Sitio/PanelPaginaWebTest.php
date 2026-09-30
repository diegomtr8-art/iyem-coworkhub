<?php

namespace Tests\Feature\Sitio;

use App\Models\Ajuste;
use App\Models\User;
use App\Models\VersionContenidoSitio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Fase 3 del módulo «Página Web»: la pantalla del panel. Ver docs/CMS-PAGINA-WEB.md.
 */
class PanelPaginaWebTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create(['name' => 'Coordinación']);
    }

    /** Lo que manda el formulario: la sección entera. */
    private function contacto(array $cambios = []): array
    {
        return $cambios + [
            'email'            => config('nodico.contacto_email'),
            'telefono'         => config('nodico.telefono'),
            'direccion'        => config('nodico.direccion'),
            'direccion_corta'  => config('nodico.direccion_corta'),
            'maps_url'         => config('nodico.maps_url'),
            'horarios'         => config('nodico.horarios'),
            'horarios_detalle' => config('nodico.horarios_detalle'),
        ];
    }

    public function test_staff_y_caja_no_alcanzan_el_modulo_ni_llamando_a_la_ruta(): void
    {
        foreach ([User::factory()->staff()->create(), User::factory()->caja()->create()] as $usuario) {
            $this->actingAs($usuario);

            $this->get(route('pagina-web.index'))->assertForbidden();
            $this->get(route('pagina-web.editar', 'general'))->assertForbidden();
            $this->put(route('pagina-web.guardar', 'contacto'), $this->contacto(['telefono' => '999 000 0000']))->assertForbidden();
            $this->post(route('pagina-web.vista-previa', 'contacto'), $this->contacto())->assertForbidden();
            $this->post(route('pagina-web.deshacer', 'contacto'))->assertForbidden();
            $this->post(route('pagina-web.restablecer', 'contacto'))->assertForbidden();
        }

        $this->assertSame(0, Ajuste::count());
    }

    public function test_sin_sesion_se_pide_entrar(): void
    {
        $this->get(route('pagina-web.index'))->assertRedirect(route('login'));
    }

    public function test_el_menu_solo_lo_ofrece_a_administracion(): void
    {
        $this->actingAs($this->admin())->get(route('pagina-web.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.permisos.gestionar-sitio', true));

        $this->actingAs(User::factory()->staff()->create())->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.permisos.gestionar-sitio', false));
    }

    public function test_la_pantalla_se_organiza_como_el_sitio_y_dice_donde_sale_cada_campo(): void
    {
        $this->actingAs($this->admin())->get(route('pagina-web.editar', 'general'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('PaginaWeb/Editar')
                ->where('pagina.titulo', 'Datos generales')
                ->where('secciones.0.clave', 'contacto')
                ->where('secciones.0.titulo', 'Datos de contacto')
                ->where('secciones.0.personalizada', false)
                ->where('secciones.0.valores.telefono', config('nodico.telefono'))
                ->where('secciones.0.campos.1.etiqueta', 'Teléfono')
                ->where('secciones.0.campos.1.maximo', 20)
                ->has('secciones.0.campos.1.ayuda')
                ->where('secciones.1.clave', 'redes'));
    }

    public function test_una_pagina_que_no_existe_da_404(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('pagina-web.editar', 'inventada'))->assertNotFound();
        $this->put(route('pagina-web.guardar', 'inventada'), [])->assertNotFound();
    }

    public function test_guardar_publica_y_deshacer_lo_devuelve(): void
    {
        $this->actingAs($admin = $this->admin());

        $this->put(route('pagina-web.guardar', 'contacto'), $this->contacto(['telefono' => '999 111 2222']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('nodico.telefono', '999 111 2222'));

        $this->assertSame($admin->id, VersionContenidoSitio::first()->guardada_por);
        $this->get(route('pagina-web.editar', 'general'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('secciones.0.personalizada', true)
            ->where('secciones.0.puedeDeshacer', true)
            ->where('secciones.0.ultimoCambio.por', 'Coordinación'));

        $this->post(route('pagina-web.deshacer', 'contacto'))->assertSessionHas('success');

        // Antes no había nada guardado: deshacer vuelve al respaldo, sin fila.
        $this->assertSame(0, Ajuste::where('clave', 'contacto')->count());
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('nodico.telefono', config('nodico.telefono')));
    }

    public function test_deshacer_vuelve_a_la_version_anterior_no_al_original(): void
    {
        $this->actingAs($this->admin());

        $this->put(route('pagina-web.guardar', 'contacto'), $this->contacto(['telefono' => '999 111 1111']));
        $this->put(route('pagina-web.guardar', 'contacto'), $this->contacto(['telefono' => '999 222 2222']));
        $this->post(route('pagina-web.deshacer', 'contacto'));

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('nodico.telefono', '999 111 1111'));
    }

    public function test_guardar_lo_mismo_no_crea_version(): void
    {
        $this->actingAs($this->admin());

        $this->put(route('pagina-web.guardar', 'contacto'), $this->contacto(['telefono' => '999 111 1111']));
        $this->put(route('pagina-web.guardar', 'contacto'), $this->contacto(['telefono' => '999 111 1111']))
            ->assertSessionHas('info');

        $this->assertSame(1, VersionContenidoSitio::count());
    }

    public function test_volver_al_original_se_puede_deshacer(): void
    {
        $this->actingAs($this->admin());

        $this->put(route('pagina-web.guardar', 'contacto'), $this->contacto(['telefono' => '999 111 1111']));
        $this->post(route('pagina-web.restablecer', 'contacto'))->assertSessionHas('success');
        $this->assertSame(0, Ajuste::where('clave', 'contacto')->count());

        $this->post(route('pagina-web.deshacer', 'contacto'));
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('nodico.telefono', '999 111 1111'));
    }

    public function test_los_errores_vuelven_por_campo_y_con_su_nombre_legible(): void
    {
        $this->actingAs($this->admin());

        $this->put(route('pagina-web.guardar', 'contacto'), $this->contacto([
            'email'    => 'no-es-correo',
            'horarios' => str_repeat('x', 61),
        ]))->assertSessionHasErrors(['email', 'horarios']);

        $errores = session('errors')->getBag('default');
        $this->assertStringContainsString('correo de contacto', $errores->first('email'));
        $this->assertSame(0, Ajuste::count());
    }

    public function test_un_campo_que_llega_como_arreglo_se_rechaza(): void
    {
        $this->actingAs($this->admin());

        $this->put(route('pagina-web.guardar', 'contacto'), $this->contacto(['telefono' => ['999', '111']]))
            ->assertSessionHasErrors('telefono');
    }

    public function test_el_texto_con_script_se_guarda_como_texto(): void
    {
        $this->actingAs($this->admin());
        $script = '<script>alert(1)</script> Lun a vie';

        $this->put(route('pagina-web.guardar', 'contacto'), $this->contacto(['horarios' => $script]))
            ->assertSessionHasNoErrors();

        // Llega al front tal cual, como texto: Vue lo escapa al pintarlo con {{ }}.
        // Lo que no puede pasar es que el servidor lo meta en HTML sin escapar.
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('nodico.horarios', $script));
        $this->get('/')->assertDontSee($script, false);
    }

    public function test_la_vista_previa_solo_la_ve_quien_edita_y_solo_si_la_pide(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('pagina-web.vista-previa', 'contacto'), $this->contacto(['telefono' => '999 333 3333']))
            ->assertSessionHas('vistaPrevia', 'contacto');

        // Nada publicado.
        $this->assertSame(0, Ajuste::count());

        // Quien edita, pidiéndola: ve el borrador y la franja de aviso.
        $this->get('/?vista_previa=1')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('nodico.telefono', '999 333 3333')
            ->where('vistaPrevia', true));

        // Quien edita, sin pedirla: ve lo publicado.
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('nodico.telefono', config('nodico.telefono'))
            ->where('vistaPrevia', false));
    }

    public function test_el_publico_no_ve_la_vista_previa_aunque_pase_el_parametro(): void
    {
        $this->actingAs($this->admin())
            ->post(route('pagina-web.vista-previa', 'contacto'), $this->contacto(['telefono' => '999 333 3333']));

        // Con la misma sesión pero ya sin permiso (p. ej. otro usuario en el mismo navegador).
        $this->actingAs(User::factory()->staff()->create())->get('/?vista_previa=1')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('nodico.telefono', config('nodico.telefono'))
                ->where('vistaPrevia', false));
    }

    public function test_la_vista_previa_valida_igual_que_guardar(): void
    {
        $this->actingAs($this->admin())
            ->post(route('pagina-web.vista-previa', 'contacto'), $this->contacto(['maps_url' => 'https://evil.example']))
            ->assertSessionHasErrors('maps_url');
    }

    public function test_pulsar_guardar_sin_tocar_nada_no_marca_la_seccion_como_editada(): void
    {
        $this->actingAs($this->admin())
            ->put(route('pagina-web.guardar', 'contacto'), $this->contacto())
            ->assertSessionHas('info');

        $this->assertSame(0, Ajuste::count());
        $this->assertSame(0, VersionContenidoSitio::count());
    }

    public function test_las_versiones_no_crecen_sin_limite(): void
    {
        $this->actingAs($this->admin());

        foreach (range(10, 35) as $n) {
            $this->put(route('pagina-web.guardar', 'contacto'), $this->contacto(['telefono' => "999 000 00{$n}"]));
        }

        $this->assertSame(20, VersionContenidoSitio::where('clave', 'contacto')->count());
    }
}
