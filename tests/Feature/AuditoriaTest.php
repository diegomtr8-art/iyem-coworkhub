<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\DirectorioEmprendedor;
use Database\Seeders\NodicoWebSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pruebas de los hallazgos corregidos en la auditoría.
 * Cada una existe para que un fallo concreto no pueda volver.
 */
class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * FE-16 — `bg-tinta/88` no generaba CSS y dejó ilegible la sección Visión
     * sin ningún aviso. Tailwind descarta en silencio lo que no sabe generar,
     * así que esta prueba vigila que no vuelva a colarse una clase inválida.
     */
    public function test_no_hay_clases_de_opacidad_invalidas(): void
    {
        $salida = [];
        $codigo = 0;
        exec('php ' . escapeshellarg(base_path('tools/verificar-clases.php')) . ' 2>&1', $salida, $codigo);

        $this->assertSame(
            0,
            $codigo,
            "tools/verificar-clases.php encontró clases inválidas:\n" . implode("\n", $salida)
        );
    }

    /** FE-16 — la página que quedó ilegible debe renderizar. */
    public function test_nosotros_renderiza(): void
    {
        $this->get(route('nosotros'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Nosotros'));
    }

    /**
     * FE-01 — los permalinks deben ser de publicación (`/p/{code}/`), no del
     * perfil: del perfil se extraía un embed no soportado que devolvía muro de
     * inicio de sesión.
     */
    public function test_los_permalinks_de_instagram_son_de_publicacion(): void
    {
        $this->seed(NodicoWebSeeder::class);

        $posts = Ajuste::obtener('instagram_posts', []);

        $this->assertNotEmpty($posts, 'El seeder debe dejar publicaciones configuradas.');

        foreach ($posts as $url) {
            $this->assertMatchesRegularExpression(
                '#^https://www\.instagram\.com/(?:p|reel|tv)/[A-Za-z0-9_-]+/?$#',
                $url,
                "Permalink no válido: {$url}"
            );
        }
    }

    /** CNT-02 — directorio y destacado deben venir de la BD, no del componente. */
    public function test_el_directorio_llega_a_la_vista_desde_la_bd(): void
    {
        $this->seed(NodicoWebSeeder::class);

        $this->get(route('actividades'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('directorio', 4)
                ->where('destacado.nombre', 'Salabtún'));
    }

    /** CNT-02 — un emprendedor inactivo no debe aparecer en el sitio. */
    public function test_un_emprendedor_inactivo_no_aparece(): void
    {
        DirectorioEmprendedor::create(['nombre' => 'Negocio retirado', 'activo' => false]);

        $this->get(route('actividades'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('directorio', 0));
    }

    /**
     * BE-02 — el límite por correo debe frenar el envío repetido, pero sin
     * bloquear a todo el coworking, que sale por una sola IP.
     */
    public function test_el_contacto_limita_por_correo(): void
    {
        Mail::fake();
        RateLimiter::clear('correo:ana@example.com');

        $datos = [
            'nombre'      => 'Ana Pérez',
            'email'       => 'ana@example.com',
            'comentarios' => 'Consulta sobre membresías.',
        ];

        // Tres envíos entran; el cuarto con el mismo correo se frena.
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('contacto.store'), $datos)->assertRedirect();
        }

        $this->post(route('contacto.store'), $datos)->assertStatus(429);

        // Otra persona desde la misma IP debe poder seguir escribiendo.
        $this->post(route('contacto.store'), [
            'nombre'      => 'Luis Gómez',
            'email'       => 'luis@example.com',
            'comentarios' => 'Quiero cotizar un salón.',
        ])->assertRedirect();
    }

    /** BE-03 — el teléfono se guarda normalizado sin perder lo que se escribió. */
    public function test_el_telefono_se_normaliza_a_e164(): void
    {
        Mail::fake();
        RateLimiter::clear('correo:tel@example.com');

        $this->post(route('contacto.store'), [
            'nombre'      => 'Ana',
            'email'       => 'tel@example.com',
            'telefono'    => '999 461 5676',
            'comentarios' => 'Hola.',
        ])->assertRedirect();

        $this->assertDatabaseHas('contactos', [
            'telefono'      => '999 461 5676',
            'telefono_e164' => '+529994615676',
        ]);
    }

    /** El honeypot descarta el envío sin delatarse. */
    public function test_el_honeypot_descarta_sin_guardar(): void
    {
        Mail::fake();

        $this->post(route('contacto.store'), [
            'nombre'      => 'Bot',
            'email'       => 'bot@example.com',
            'comentarios' => 'spam',
            'sitio_web'   => 'http://spam.example',
        ])->assertRedirect()->assertSessionHas('contacto_ok', true);

        $this->assertDatabaseCount('contactos', 0);
        Mail::assertNothingSent();
    }

    /** SEO-05 — el sitemap debe listar las cinco públicas y las legales. */
    public function test_el_sitemap_lista_todas_las_paginas(): void
    {
        $respuesta = $this->get('/sitemap.xml')->assertOk();
        $respuesta->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $host = rtrim(url('/'), '/');

        // La raíz conserva su barra; el resto no la lleva.
        foreach (['/', '/nosotros', '/membresias', '/eventos', '/actividades', '/aviso-de-privacidad', '/terminos'] as $ruta) {
            $respuesta->assertSee('<loc>' . $host . $ruta . '</loc>', false);
        }
    }

    /**
     * Rendimiento — el chunk de la página se importa dinámicamente desde
     * `app.js`, así que el navegador no sabía que existía hasta haber
     * ejecutado la entrada: un segundo viaje completo antes de pintar nada.
     * La vista raíz debe declararlo por adelantado.
     */
    public function test_la_vista_raiz_precarga_los_modulos_de_la_pagina(): void
    {
        if (! is_file(public_path('build/manifest.json'))) {
            $this->markTestSkipped('Hace falta `npm run build`.');
        }

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, 'rel="modulepreload"'),
            'La portada debe declarar sus módulos antes de ejecutar app.js.'
        );

        // El póster del hero tampoco se pedía hasta que Vue montaba la sección.
        $this->assertStringContainsString('as="image"', $html);
        $this->assertStringContainsString('hero-inicio', $html);
    }

    /**
     * SEO-02 — el sitio vive sin www, y la canónica no puede depender del host
     * que mande quien pide: con `trustProxies(at: '*')`, `X-Forwarded-Host`
     * dejaba elegir el host que salía en la canónica, en `og:url` y en el
     * JSON-LD.
     */
    public function test_la_canonica_ignora_el_host_de_la_peticion(): void
    {
        config(['nodico.host_canonico' => 'https://www.nodico.com.mx/']);

        $this->get('/nosotros', ['X-Forwarded-Host' => 'sitio-de-otro.example'])
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('seo.origen', 'https://nodico.com.mx')
                ->where('seo.canonica', 'https://nodico.com.mx/nosotros')
                ->where('seo.negocio.url', 'https://nodico.com.mx'));
    }

    /** SEO-02 — sin `host_canonico`, el origen sale de `app.url`, no del host. */
    public function test_la_canonica_cae_a_app_url(): void
    {
        config(['nodico.host_canonico' => null, 'app.url' => 'https://nodico.com.mx']);

        $this->get('/membresias', ['X-Forwarded-Host' => 'sitio-de-otro.example'])
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('seo.canonica', 'https://nodico.com.mx/membresias'));
    }

    /**
     * SEO-01 — los metadatos sociales tienen que venir en el HTML. WhatsApp,
     * Slack, Telegram, LinkedIn y Twitter/X no ejecutan JavaScript, así que
     * mientras `Meta.vue` era el único que los ponía, compartir un enlace no
     * mostraba tarjeta y las imágenes 1200x630 no las veía nadie.
     */
    #[DataProvider('rutasPublicas')]
    public function test_los_metadatos_sociales_vienen_en_el_html(string $ruta, string $imagen): void
    {
        config(['nodico.host_canonico' => 'https://nodico.com.mx']);

        $html = $this->get($ruta)->assertOk()->getContent();

        foreach (['og:title', 'og:description', 'og:url', 'og:image', 'twitter:card'] as $etiqueta) {
            $this->assertStringContainsString($etiqueta, $html, "Falta {$etiqueta} en {$ruta}");
        }

        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('name="description"', $html);

        // La imagen social debe ser absoluta: un scraper no resuelve relativas.
        $this->assertStringContainsString(
            'content="https://nodico.com.mx/img/og/' . $imagen . '.jpg"',
            $html
        );
    }

    public static function rutasPublicas(): array
    {
        return [
            'portada'     => ['/', 'home'],
            'nosotros'    => ['/nosotros', 'nosotros'],
            'membresias'  => ['/membresias', 'membresias'],
            'eventos'     => ['/eventos', 'eventos'],
            'actividades' => ['/actividades', 'actividades'],
        ];
    }

    /** SEO-04 — el título del HTML ya lleva el sufijo, sin esperar al JavaScript. */
    public function test_el_titulo_viene_completo_en_el_html(): void
    {
        $this->get('/membresias')
            ->assertOk()
            ->assertSee('<title inertia>Membresías y precios — Nódico</title>', false);
    }

    /** BE-04 — los textos legales salen de archivos, no del controlador. */
    public function test_las_paginas_legales_vienen_de_markdown(): void
    {
        $this->get(route('privacidad'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Legal/Documento')
                ->where('provisional', true)
                ->where('titulo', 'Aviso de privacidad')
                ->where('contenido', fn ($html) => str_contains($html, '<h2>')));
    }
}
