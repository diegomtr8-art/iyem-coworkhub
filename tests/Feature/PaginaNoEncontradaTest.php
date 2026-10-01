<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hallazgo de pruebas: «falta la pantalla de 404». Una dirección que no
 * existe enseñaba la página por defecto de Laravel, en inglés y sin salida.
 * Ahora es una pantalla de Nódico que dice qué pasó y a dónde ir.
 */
class PaginaNoEncontradaTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_direccion_que_no_existe_ensena_la_pantalla_de_nodico(): void
    {
        $this->get('/esto-no-existe')
            ->assertNotFound()
            ->assertInertia(fn ($pagina) => $pagina->component('Errors/404'));
    }

    public function test_con_sesion_tambien_y_sabe_a_que_seccion_mandarte(): void
    {
        $recepcion = User::factory()->create(['tipo' => RolUsuario::Staff->value]);

        $this->actingAs($recepcion)
            ->get('/esto-no-existe')
            ->assertNotFound()
            ->assertInertia(fn ($pagina) => $pagina->component('Errors/404')
                ->where('auth.user.id', $recepcion->id)
                ->has('auth.user.portalRuta'));
    }

    public function test_recepcion_en_reportes_no_ve_la_pagina_de_laravel(): void
    {
        $recepcion = User::factory()->create(['tipo' => RolUsuario::Staff->value]);

        $respuesta = $this->actingAs($recepcion)->get('/reportes');

        // Sea 403 o 404, tiene que ser una pantalla de Nódico.
        $this->assertContains($respuesta->status(), [403, 404]);
        $respuesta->assertInertia(fn ($pagina) => $pagina->component('Errors/'.$respuesta->status()));
    }

    public function test_un_modelo_que_no_existe_tambien_usa_la_pantalla(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/miembros/999999')
            ->assertNotFound()
            ->assertInertia(fn ($pagina) => $pagina->component('Errors/404'));
    }

    public function test_la_api_sigue_respondiendo_json(): void
    {
        $this->getJson('/api/v1/esto-no-existe')
            ->assertNotFound()
            ->assertJson(['message' => 'No encontramos eso.']);
    }
}
