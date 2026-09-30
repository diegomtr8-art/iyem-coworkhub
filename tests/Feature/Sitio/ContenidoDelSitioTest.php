<?php

namespace Tests\Feature\Sitio;

use App\Mail\ContactoRecibido;
use App\Models\Ajuste;
use App\Servicios\Sitio\ContenidoDelSitio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Fase 1 del módulo «Página Web» (docs/CMS-PAGINA-WEB.md): el contenido se lee
 * de `ajustes` con respaldo, y nada de lo que haya en esa tabla puede tumbar
 * la página pública.
 */
class ContenidoDelSitioTest extends TestCase
{
    use RefreshDatabase;

    private function sitio(): ContenidoDelSitio
    {
        return app(ContenidoDelSitio::class);
    }

    /** La forma de `nodico` que ya leen el pie, «Hablemos» y el hero. */
    private function nodicoDeHoy(): array
    {
        return [
            'email'           => config('nodico.contacto_email'),
            'telefono'        => config('nodico.telefono'),
            'telefonoE164'    => config('nodico.telefono_e164'),
            'direccion'       => config('nodico.direccion'),
            'direccionCorta'  => config('nodico.direccion_corta'),
            'mapsUrl'         => config('nodico.maps_url'),
            'mapsEmbed'       => config('nodico.maps_embed'),
            'horarios'        => config('nodico.horarios'),
            'horariosDetalle' => config('nodico.horarios_detalle'),
            'redes'           => config('nodico.redes'),
            'instagram'       => config('nodico.instagram_handle'),
        ];
    }

    public function test_con_la_tabla_vacia_el_sitio_se_ve_igual_que_antes(): void
    {
        $this->assertSame(0, Ajuste::count());

        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('nodico', $this->nodicoDeHoy()));
    }

    public function test_guardar_cambia_el_sitio_en_la_siguiente_visita(): void
    {
        $this->get('/')->assertOk(); // calienta la caché con el respaldo

        $this->sitio()->guardar('contacto', [
            'email'           => 'hola@nodico.com.mx',
            'telefono'        => '999 123 4567',
            'direccion'       => 'Calle 60 #123, Centro, Mérida',
            'direccion_corta' => 'Centro de Mérida',
            'maps_url'        => 'https://maps.app.goo.gl/abc123',
            'horarios'        => 'Lunes a sábado, 8:00 a 20:00 h',
            'horarios_detalle' => null,
        ]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('nodico.email', 'hola@nodico.com.mx')
            ->where('nodico.telefono', '999 123 4567')
            ->where('nodico.telefonoE164', '+529991234567')
            ->where('nodico.direccionCorta', 'Centro de Mérida')
            ->where('nodico.horariosDetalle', null)
            // Lo que no se tocó sigue igual.
            ->where('nodico.redes', config('nodico.redes')));
    }

    public function test_borrar_la_fila_devuelve_el_respaldo(): void
    {
        $this->sitio()->guardar('redes', ['instagram_usuario' => 'otra.cuenta']);
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('nodico.instagram', 'otra.cuenta'));

        Ajuste::where('clave', 'redes')->first()->delete();

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('nodico.instagram', config('nodico.instagram_handle')));
    }

    public function test_un_campo_guardado_a_medias_se_completa_con_su_respaldo(): void
    {
        Ajuste::guardar('contacto', ['telefono' => '999 000 1111']);

        $this->assertSame('999 000 1111', $this->sitio()->valor('contacto', 'telefono'));
        $this->assertSame(config('nodico.contacto_email'), $this->sitio()->valor('contacto', 'email'));
    }

    public function test_un_valor_corrupto_no_rompe_la_pagina_y_se_sirve_el_respaldo(): void
    {
        Log::spy();

        // Escritas por fuera del servicio, como lo haría un error humano en tinker.
        Ajuste::guardar('contacto', [
            'email'    => 'esto no es un correo',
            'telefono' => '999 000 1111',
            'maps_url' => 'javascript:alert(1)',
        ]);
        Ajuste::guardar('redes', [
            'facebook'          => 'https://sitio-malicioso.example/facebook',
            'instagram_usuario' => '"><script>alert(1)</script>',
        ]);

        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('nodico.email', config('nodico.contacto_email'))
            ->where('nodico.mapsUrl', config('nodico.maps_url'))
            ->where('nodico.telefono', '999 000 1111') // el campo válido sí se usa
            ->where('nodico.redes.facebook', config('nodico.redes.facebook'))
            ->where('nodico.instagram', config('nodico.instagram_handle')));

        Log::shouldHaveReceived('warning')->withArgs(fn ($mensaje) => str_contains($mensaje, '«contacto»'));
        Log::shouldHaveReceived('warning')->withArgs(fn ($mensaje) => str_contains($mensaje, '«redes»'));
    }

    public function test_una_fila_que_no_es_un_objeto_se_ignora_entera(): void
    {
        DB::table('ajustes')->insert(['clave' => 'contacto', 'valor' => json_encode('texto suelto'), 'created_at' => now(), 'updated_at' => now()]);

        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('nodico', $this->nodicoDeHoy()));
    }

    public function test_sin_la_tabla_la_portada_responde_con_el_respaldo(): void
    {
        Schema::drop('ajustes');

        $this->get('/')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('nodico', $this->nodicoDeHoy()));
    }

    public function test_guardar_rechaza_lo_que_no_cumple_las_reglas(): void
    {
        $casos = [
            ['contacto', ['email' => 'no-es-correo']],
            ['contacto', ['telefono' => '12345']],
            ['contacto', ['maps_url' => 'http://maps.app.goo.gl/abc']],       // sin https
            ['contacto', ['maps_url' => 'https://evil.example/maps']],        // otro host
            ['contacto', ['horarios' => str_repeat('x', 61)]],                // rompe la maquetación
            ['redes', ['linkedin' => 'https://www.facebook.com/nodicomx']],   // red equivocada
            ['redes', ['instagram_usuario' => 'nodico mx']],
        ];

        foreach ($casos as [$clave, $valor]) {
            try {
                $this->sitio()->guardar($clave, $valor);
                $this->fail('Se aceptó ' . json_encode($valor));
            } catch (ValidationException) {
                // esperado
            }
        }

        $this->assertSame(0, Ajuste::count());
    }

    public function test_guardar_descarta_campos_que_no_estan_en_el_catalogo(): void
    {
        $this->sitio()->guardar('redes', ['instagram_usuario' => 'otra.cuenta', 'inyectado' => '<b>hola</b>']);

        $guardado = Ajuste::where('clave', 'redes')->value('valor');

        $this->assertSame('otra.cuenta', $guardado['instagram_usuario']);
        $this->assertArrayNotHasKey('inyectado', $guardado);
    }

    public function test_una_seccion_fuera_del_catalogo_no_existe(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->sitio()->seccion('inventada');
    }

    public function test_la_portada_no_consulta_ajustes_en_cada_visita(): void
    {
        $this->get('/')->assertOk(); // primera visita: llena la caché

        DB::enableQueryLog();
        $this->get('/')->assertOk();
        $this->get('/nosotros')->assertOk();

        $consultas = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '"ajustes"'));
        $this->assertCount(0, $consultas, 'La lectura del contenido tiene que salir de la caché.');
    }

    public function test_el_formulario_de_contacto_llega_al_correo_editado(): void
    {
        Mail::fake();
        $this->sitio()->guardar('contacto', ['email' => 'coordinacion@nodico.com.mx']);

        $this->post(route('contacto.store'), [
            'nombre'      => 'Ana',
            'email'       => 'ana@example.com',
            'comentarios' => 'Quiero conocer el espacio.',
        ]);

        Mail::assertSent(ContactoRecibido::class, fn ($correo) => $correo->hasTo('coordinacion@nodico.com.mx'));
    }

    public function test_el_json_ld_usa_los_datos_editados(): void
    {
        $this->sitio()->guardar('contacto', ['telefono' => '(999) 555-0000']);
        $this->sitio()->guardar('redes', ['linkedin' => null]);

        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('seo.negocio.telephone', '+529995550000')
            ->where('seo.negocio.sameAs', array_values(array_filter([
                config('nodico.redes.instagram'),
                config('nodico.redes.facebook'),
            ]))));
    }
}
