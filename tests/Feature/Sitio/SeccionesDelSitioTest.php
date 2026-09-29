<?php

namespace Tests\Feature\Sitio;

use App\Models\User;
use App\Servicios\Sitio\ContenidoDelSitio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Fase 4 del módulo «Página Web»: cada sección migrada llega a su página con
 * su respaldo y se puede cambiar desde el panel. Que el sitio se vea idéntico
 * con la tabla vacía se comprueba además en el navegador (docs/CMS-PAGINA-WEB.md).
 */
class SeccionesDelSitioTest extends TestCase
{
    use RefreshDatabase;

    private function sitio(): ContenidoDelSitio
    {
        return app(ContenidoDelSitio::class);
    }

    private function rechaza(string $clave, array $valor, string $campo): void
    {
        try {
            $this->sitio()->guardar($clave, $valor);
            $this->fail('Se aceptó ' . json_encode($valor));
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($campo, $e->errors(), json_encode($e->errors()));
        }
    }

    // --- 4.1 · Contacto y enlaces -------------------------------------------

    public function test_el_video_de_la_portada_sale_del_modulo(): void
    {
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('contenido.hero.video_youtube', 'Ml4sprGUqzc'));

        $this->sitio()->guardar('inicio.hero', ['video_youtube' => 'dQw4w9WgXcQ']);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('contenido.hero.video_youtube', 'dQw4w9WgXcQ'));
    }

    public function test_el_video_solo_acepta_el_id_no_una_url_ni_un_embed(): void
    {
        $this->rechaza('inicio.hero', ['video_youtube' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'], 'video_youtube');
        $this->rechaza('inicio.hero', ['video_youtube' => '"><script>'], 'video_youtube');
    }

    public function test_los_aliados_se_comparten_con_todas_las_paginas(): void
    {
        foreach (['/', '/nosotros', '/actividades'] as $ruta) {
            $this->get($ruta)->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comun.aliados.etiqueta', 'Con el respaldo de')
                ->where('comun.aliados.iyem_url', 'https://iyem.yucatan.gob.mx')
                ->where('comun.aliados.canieti_url', null));
        }

        $this->sitio()->guardar('comun.aliados', ['canieti_url' => 'https://www.canieti.org']);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('comun.aliados.canieti_url', 'https://www.canieti.org'));
    }

    public function test_un_enlace_de_aliado_tiene_que_ser_https(): void
    {
        $this->rechaza('comun.aliados', ['iyem_url' => 'http://iyem.yucatan.gob.mx'], 'iyem_url');
        $this->rechaza('comun.aliados', ['iyem_url' => 'javascript:alert(1)'], 'iyem_url');
    }

    public function test_la_ficha_para_buscadores_sale_del_modulo_y_el_horario_de_las_reglas_de_reserva(): void
    {
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('seo.negocio.address.streetAddress', 'Avenida Principal, Industrias No Contaminantes 13613')
            ->where('seo.negocio.geo.latitude', 21.0527159)
            ->where('seo.negocio.openingHoursSpecification.0.dayOfWeek', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])
            ->where('seo.negocio.openingHoursSpecification.0.opens', '09:00'));

        config(['nodico.operacion.cierre' => '20:00']);
        $this->sitio()->guardar('negocio', ['calle' => 'Calle 60 #500', 'latitud' => 20.97]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('seo.negocio.address.streetAddress', 'Calle 60 #500')
            ->where('seo.negocio.geo.latitude', 20.97)
            ->where('seo.negocio.openingHoursSpecification.0.closes', '20:00'));
    }

    public function test_coordenadas_fuera_de_la_peninsula_se_rechazan(): void
    {
        $this->rechaza('negocio', ['latitud' => 210.527], 'latitud');
        $this->rechaza('negocio', ['longitud' => 89.64], 'longitud');
        $this->rechaza('negocio', ['codigo_postal' => '9711'], 'codigo_postal');
    }

    public function test_los_eventos_de_comunidad_usan_la_direccion_de_la_ficha(): void
    {
        $this->get(route('actividades'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('lugarEventos', 'Hacienda Sodzil Nte., Mérida, Yucatán'));
    }

    public function test_cada_pagina_del_panel_se_abre(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach (array_keys(\App\Servicios\Sitio\CatalogoDelSitio::paginas()) as $pagina) {
            $this->get(route('pagina-web.editar', $pagina))->assertOk();
        }
    }
}
