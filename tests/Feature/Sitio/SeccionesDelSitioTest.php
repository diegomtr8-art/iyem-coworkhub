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

    // --- Invariantes del catálogo --------------------------------------------

    /**
     * Si un respaldo no cumple sus reglas, la sección no se puede guardar
     * nunca (guardar valida la sección entera) y nadie lo nota hasta que
     * alguien intenta cambiar otro campo. Pasó con las descripciones SEO.
     */
    public function test_todos_los_respaldos_cumplen_sus_propias_reglas(): void
    {
        foreach (\App\Servicios\Sitio\CatalogoDelSitio::secciones() as $clave => $seccion) {
            $campos = $seccion['campos'];
            $validador = \Illuminate\Support\Facades\Validator::make(
                $this->sitio()->seccion($clave),
                \App\Servicios\Sitio\Campo::reglas($campos),
            );

            $this->assertSame([], $validador->errors()->toArray(), "El respaldo de «{$clave}» no cumple sus reglas.");
        }
    }

    public function test_toda_seccion_se_presenta_y_pertenece_a_una_pagina_del_panel(): void
    {
        $paginas = \App\Servicios\Sitio\CatalogoDelSitio::paginas();

        foreach (\App\Servicios\Sitio\CatalogoDelSitio::secciones() as $clave => $seccion) {
            $this->assertArrayHasKey($seccion['pagina'], $paginas, $clave);
            $this->assertIsArray($this->sitio()->presentar($clave));
        }
    }

    // --- 4.2 · Textos ---------------------------------------------------------

    public function test_los_textos_de_cada_pagina_llegan_con_su_respaldo(): void
    {
        $casos = [
            '/'            => ['contenido.hero.titulo', 'Donde el trabajo es un pretexto para crear'],
            '/nosotros'    => ['contenido.vision.titulo', 'El referente del sureste de México'],
            '/membresias'  => ['contenido.pasos.titulo', 'De la compra al escritorio'],
            '/eventos'     => ['contenido.coffee.titulo', 'Coffee break para tu evento'],
            '/actividades' => ['contenido.talleres.titulo', 'Conoce los talleres del mes'],
        ];

        foreach ($casos as $ruta => [$prop, $texto]) {
            $this->get($ruta)->assertInertia(fn (AssertableInertia $page) => $page
                ->where($prop, $texto)
                ->where('comun.hablemos.titulo', 'Hablemos')
                ->where('comun.pie.llamado_titulo', '¿Listo para empezar?'));
        }
    }

    public function test_cambiar_un_texto_cambia_la_pagina(): void
    {
        $this->sitio()->guardar('nosotros.mision', ['parrafo2' => null]);
        $this->sitio()->guardar('comun.pie', ['llamado_titulo' => '¿Empezamos?']);

        $this->get('/nosotros')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('contenido.mision.parrafo2', null)
            ->where('comun.pie.llamado_titulo', '¿Empezamos?'));
    }

    public function test_el_titulo_para_buscadores_sale_en_el_html_del_servidor(): void
    {
        $this->get('/nosotros')->assertSee('<title inertia>Nosotros — Nódico</title>', false);

        $this->sitio()->guardar('buscadores.nosotros', [
            'titulo'      => 'Quiénes somos',
            'descripcion' => 'La comunidad de emprendedores del IYEM en Mérida.',
        ]);

        // Lo leen WhatsApp y Google sin ejecutar JavaScript: tiene que estar en el HTML.
        $this->get('/nosotros')
            ->assertSee('<title inertia>Quiénes somos — Nódico</title>', false)
            ->assertSee('content="La comunidad de emprendedores del IYEM en Mérida."', false);
    }

    public function test_un_titulo_largo_para_su_hueco_se_rechaza(): void
    {
        $this->rechaza('inicio.hero', ['titulo' => str_repeat('palabra ', 10)], 'titulo');
        $this->rechaza('buscadores.home', ['titulo' => str_repeat('x', 61)], 'titulo');
    }

    public function test_el_coffee_break_muestra_los_precios_del_cotizador(): void
    {
        config(['nodico.salones.coffee' => [['hasta_pax' => 25, 'precio' => 50], ['desde_pax' => 100, 'precio' => 40]]]);

        $this->get('/eventos')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('coffee.0.precio', 50)
            ->where('coffee.1.desde_pax', 100));
    }

    // --- 4.3 · Listas ---------------------------------------------------------

    private function valores(int $cuantos): array
    {
        return array_map(fn ($n) => ['titulo' => "Valor {$n}", 'descripcion' => "Texto {$n}"], range(1, $cuantos));
    }

    public function test_las_listas_llegan_con_su_respaldo(): void
    {
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('contenido.servicios.elementos', 6)
            ->where('contenido.servicios.elementos.1.titulo', 'Sala profesional de creación de contenido')
            ->where('contenido.espacios.elementos.1.cantidad', '1 disponible')
            ->where('contenido.beneficios.elementos.0.titulo_corto', 'Descuentos'));

        $this->get('/membresias')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('contenido.incluido.elementos', 4)
            ->has('contenido.pasos.elementos', 3));
    }

    public function test_una_lista_respeta_sus_limites_y_su_multiplo(): void
    {
        $this->rechaza('nosotros.valores', ['elementos' => $this->valores(2)], 'elementos');
        $this->rechaza('nosotros.valores', ['elementos' => $this->valores(5)], 'elementos');
        $this->rechaza('nosotros.valores', ['elementos' => $this->valores(9)], 'elementos');
        $this->rechaza('inicio.servicios', ['elementos' => array_slice($this->valores(6), 0, 5)], 'elementos');

        $this->sitio()->guardar('nosotros.valores', ['elementos' => $this->valores(3)]);

        $this->get('/nosotros')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('contenido.valores.elementos', 3)
            ->where('contenido.valores.elementos.2.titulo', 'Valor 3'));
    }

    public function test_cada_elemento_se_valida_y_el_error_dice_cual(): void
    {
        $elementos = $this->valores(3);
        $elementos[1]['titulo'] = str_repeat('x', 41);

        $this->rechaza('nosotros.valores', ['elementos' => $elementos], 'elementos.1.titulo');
    }

    public function test_lo_que_no_es_del_catalogo_no_se_guarda_dentro_de_un_elemento(): void
    {
        $elementos = $this->valores(3);
        $elementos[0]['icono'] = '/img/otro-icono.webp';
        $elementos[0]['html'] = '<script>alert(1)</script>';

        $this->sitio()->guardar('nosotros.valores', ['elementos' => $elementos]);

        $guardado = \App\Models\Ajuste::where('clave', 'nosotros.valores')->value('valor');
        $this->assertSame(['titulo' => 'Valor 1', 'descripcion' => 'Texto 1'], $guardado['elementos'][0]);
    }

    public function test_una_lista_corrupta_se_sirve_entera_desde_el_respaldo(): void
    {
        // Un elemento roto no deja una lista a medias: vuelve la lista entera.
        \App\Models\Ajuste::guardar('nosotros.valores', [
            'titulo'    => 'Lo que nos mueve',
            'elementos' => [['titulo' => 'Solo uno', 'descripcion' => 'Y sin compañeros']],
        ]);

        $this->get('/nosotros')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('contenido.valores.elementos', 6)
            ->where('contenido.valores.elementos.0.titulo', 'Creatividad'));
    }

    public function test_el_panel_describe_las_listas_con_sus_limites(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('pagina-web.editar', 'nosotros'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('secciones.3.campos.1.tipo', 'lista')
                ->where('secciones.3.campos.1.minimo', 3)
                ->where('secciones.3.campos.1.maximo', 6)
                ->where('secciones.3.campos.1.multiplo', 3)
                ->where('secciones.3.campos.1.campos.0.maximo', 40));
    }

    // --- 4.4 · Imágenes -------------------------------------------------------

    private function subir(string $formato, int $ancho, int $alto): \App\Models\ImagenSitio
    {
        return app(\App\Servicios\Sitio\ProcesadorDeImagenes::class)
            ->subir(\Illuminate\Http\UploadedFile::fake()->image('foto.jpg', $ancho, $alto), $formato);
    }

    public function test_las_fotos_llegan_presentadas_con_la_fija_de_respaldo(): void
    {
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('contenido.hero.imagen.src', '/img/nodico/hero-inicio.webp')
            ->where('contenido.hero.imagen.alt', 'Área de coworking de Nódico en Mérida')
            ->where('contenido.espacios.elementos.2.foto.src', '/img/nodico/espacio-contenido.webp')
            ->where('contenido.espacios.elementos.2.foto.width', 1600)
            ->where('contenido.servicios.foto_1.srcset', null)
            ->where('comun.acceso.fondo.src', '/img/nodico/comunidad-fondo.webp'));
    }

    public function test_una_foto_subida_sustituye_a_la_fija_con_sus_tamanos(): void
    {
        \Illuminate\Support\Facades\Storage::fake('medios', ['url' => '/medios']);
        $imagen = $this->subir('tarjeta', 2400, 1800);

        $this->sitio()->guardar('inicio.daypass', ['imagen' => ['id' => $imagen->id, 'alt' => 'Dos emprendedoras en la mesa larga']]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('contenido.daypass.imagen.src', "/medios/sitio/{$imagen->carpeta}/1600.webp")
            ->where('contenido.daypass.imagen.srcset', "/medios/sitio/{$imagen->carpeta}/800.webp 800w, /medios/sitio/{$imagen->carpeta}/1600.webp 1600w")
            ->where('contenido.daypass.imagen.alt', 'Dos emprendedoras en la mesa larga'));
    }

    public function test_una_foto_de_otro_formato_o_inexistente_se_rechaza(): void
    {
        \Illuminate\Support\Facades\Storage::fake('medios', ['url' => '/medios']);
        $vertical = $this->subir('retrato', 1200, 2200);

        $this->rechaza('inicio.daypass', ['imagen' => ['id' => $vertical->id, 'alt' => 'x']], 'imagen');
        $this->rechaza('inicio.daypass', ['imagen' => ['id' => 99999, 'alt' => 'x']], 'imagen');
    }

    public function test_una_foto_con_contenido_necesita_descripcion_y_una_decorativa_no(): void
    {
        $this->rechaza('inicio.daypass', ['imagen' => ['id' => null, 'alt' => '  ']], 'imagen');

        $this->sitio()->guardar('nosotros.vision', ['imagen' => ['id' => null, 'alt' => '']]);
        $this->assertTrue($this->sitio()->personalizada('nosotros.vision'));
    }

    public function test_si_la_foto_subida_desaparece_se_sirve_la_fija(): void
    {
        \Illuminate\Support\Facades\Storage::fake('medios', ['url' => '/medios']);
        $imagen = $this->subir('horizontal', 1800, 1200);
        $this->sitio()->guardar('nosotros.portada', ['imagen' => ['id' => $imagen->id, 'alt' => 'La comunidad']]);

        \App\Models\ImagenSitio::whereKey($imagen->id)->delete();
        $this->sitio()->olvidar();

        $this->get('/nosotros')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('contenido.portada.imagen.src', '/img/nodico/nosotros-hero.webp'));
    }

    public function test_un_espacio_nuevo_no_puede_quedarse_sin_foto(): void
    {
        \Illuminate\Support\Facades\Storage::fake('medios', ['url' => '/medios']);
        $elementos = $this->sitio()->seccion('inicio.espacios')['elementos'];
        $nuevo = ['foto' => ['id' => null, 'alt' => 'Terraza'], 'nombre' => 'Terraza', 'cantidad' => '1', 'capacidad' => 'Hasta 20', 'descripcion' => 'Al aire libre.'];

        // Seis en pares, pero los dos nuevos sin foto: no hay fija para esas posiciones.
        $this->rechaza('inicio.espacios', ['elementos' => [...$elementos, $nuevo, $nuevo]], 'elementos.4.foto');

        $foto = $this->subir('tarjeta', 1600, 1200);
        $conFoto = ['foto' => ['id' => $foto->id, 'alt' => 'Terraza']] + $nuevo;
        $this->sitio()->guardar('inicio.espacios', ['elementos' => [...$elementos, $conFoto, $conFoto]]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->has('contenido.espacios.elementos', 6));
    }

    public function test_los_espacios_van_de_dos_en_dos(): void
    {
        $elementos = $this->sitio()->seccion('inicio.espacios')['elementos'];

        $this->rechaza('inicio.espacios', ['elementos' => array_slice($elementos, 0, 3)], 'elementos');
        $this->sitio()->guardar('inicio.espacios', ['elementos' => array_slice($elementos, 0, 2)]);
    }

    public function test_la_imagen_social_se_edita_y_sale_absoluta_en_el_html(): void
    {
        \Illuminate\Support\Facades\Storage::fake('medios', ['url' => '/medios']);
        $this->get('/nosotros')->assertSee('/img/og/nosotros.jpg', false);

        $og = $this->subir('social', 2000, 1100);
        $this->sitio()->guardar('buscadores.nosotros', ['imagen' => ['id' => $og->id, 'alt' => '']]);

        $this->get('/nosotros')->assertSee(url("/medios/sitio/{$og->carpeta}/1200.jpg"), false);
    }

    public function test_subir_desde_el_panel_devuelve_la_foto_y_no_la_publica(): void
    {
        \Illuminate\Support\Facades\Storage::fake('medios', ['url' => '/medios']);
        $this->actingAs(User::factory()->admin()->create());

        $respuesta = $this->post(route('pagina-web.imagenes', 'tarjeta'), [
            'imagen' => \Illuminate\Http\UploadedFile::fake()->image('foto.jpg', 1600, 1200),
        ])->assertOk()->assertJsonStructure(['id', 'src', 'srcset', 'width', 'height']);

        $this->assertStringStartsWith('/medios/sitio/', $respuesta->json('src'));
        $this->assertFalse($this->sitio()->personalizada('inicio.daypass'));
    }

    public function test_subir_desde_el_panel_valida_y_explica(): void
    {
        \Illuminate\Support\Facades\Storage::fake('medios', ['url' => '/medios']);
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson(route('pagina-web.imagenes', 'tarjeta'), [
            'imagen' => \Illuminate\Http\UploadedFile::fake()->image('chica.jpg', 600, 400),
        ])->assertStatus(422)->assertJsonPath('errors.imagen.0', fn ($m) => str_contains($m, 'demasiado pequeña'));

        $this->post(route('pagina-web.imagenes', 'inventado'), [])->assertNotFound();
    }

    public function test_staff_no_puede_subir_fotos(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->post(route('pagina-web.imagenes', 'tarjeta'), [
                'imagen' => \Illuminate\Http\UploadedFile::fake()->image('foto.jpg', 1600, 1200),
            ])->assertForbidden();

        $this->assertSame(0, \App\Models\ImagenSitio::count());
    }

    public function test_cada_pagina_del_panel_se_abre(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach (array_keys(\App\Servicios\Sitio\CatalogoDelSitio::paginas()) as $pagina) {
            $this->get(route('pagina-web.editar', $pagina))->assertOk();
        }
    }
}
