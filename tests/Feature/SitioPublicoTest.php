<?php

namespace Tests\Feature;

use App\Mail\ContactoRecibido;
use App\Models\Contacto;
use App\Models\Espacio;
use App\Models\Plane;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SitioPublicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_rutas_publicas_responden(): void
    {
        $rutas = [
            '/'            => 'Welcome',
            '/nosotros'    => 'Nosotros',
            '/membresias'  => 'Membresias',
            '/eventos'     => 'Salones',
            '/actividades' => 'Comunidad',
        ];

        foreach ($rutas as $url => $componente) {
            $this->get($url)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component($componente));
        }
    }

    public function test_eventos_y_actividades_son_paginas_distintas(): void
    {
        $this->get(route('eventos'))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Salones')->has('salones'));

        $this->get(route('actividades'))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Comunidad')->has('lumaEmbed'));
    }

    public function test_membresias_muestra_los_planes_con_datos_de_la_bd(): void
    {
        $this->seed(\Database\Seeders\NodicoWebSeeder::class);

        $this->get(route('membresias'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Membresias')
                ->has('planes', 4)
                ->where('planes.0.nombre', 'Day-Pass')
                // SQLite devuelve el decimal como entero; se compara el valor numérico.
                ->where('planes.0.precio', fn ($precio) => (float) $precio === 79.0)
                ->where('planes.0.stripe_url', 'https://buy.stripe.com/00waER7JU4Lp65v0gb6Zy05')
                ->has('planes.0.beneficios', 3));
    }

    public function test_los_salones_publicados_llegan_a_la_vista(): void
    {
        $this->seed(\Database\Seeders\NodicoWebSeeder::class);

        $this->get(route('eventos'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('salones', 2)
                ->where('salones.0.nombre', 'Yucatán Emprende 1')
                ->where('salones.0.capacidad', 120)
                ->has('salones.0.incluye', 11));
    }

    public function test_un_espacio_no_publicado_no_aparece_en_el_sitio(): void
    {
        Espacio::create([
            'nombre'    => 'Salón en borrador',
            'tipo'      => 'salon_eventos',
            'capacidad' => 50,
            'publicado' => false,
        ]);

        $this->get(route('eventos'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('salones', 0));
    }

    public function test_un_plan_inactivo_no_aparece_en_el_sitio(): void
    {
        Plane::create(['nombre' => 'Plan retirado', 'tipo' => 'mes', 'precio' => 100, 'activo' => false]);

        $this->get(route('membresias'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('planes', 0));
    }

    public function test_el_formulario_de_contacto_guarda_y_envia_correo(): void
    {
        Mail::fake();

        $this->post(route('contacto.store'), [
            'nombre'      => 'Ana Pérez',
            'telefono'    => '9991234567',
            'email'       => 'ana@example.com',
            'empresa'     => 'Estudio Ana',
            'asunto'      => 'Cotización de salón',
            'comentarios' => 'Quisiera rentar el salón para un taller de 40 personas.',
        ])->assertRedirect()->assertSessionHas('contacto_ok', true);

        $this->assertDatabaseHas('contactos', [
            'nombre' => 'Ana Pérez',
            'email'  => 'ana@example.com',
            'asunto' => 'Cotización de salón',
        ]);

        Mail::assertSent(ContactoRecibido::class, fn ($mail) => $mail->hasTo(config('nodico.contacto_email')));
    }

    public function test_el_formulario_de_contacto_valida_los_campos_obligatorios(): void
    {
        Mail::fake();

        $this->post(route('contacto.store'), ['nombre' => '', 'email' => 'no-es-un-correo'])
            ->assertSessionHasErrors(['nombre', 'email', 'comentarios']);

        $this->assertSame(0, Contacto::count());
        Mail::assertNothingSent();
    }

    public function test_robots_bloquea_la_indexacion_fuera_de_produccion(): void
    {
        // El entorno de pruebas no es 'production', así que debe bloquear todo.
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /');
    }
}
