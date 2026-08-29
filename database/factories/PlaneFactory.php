<?php

namespace Database\Factories;

use App\Models\Plane;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Los cuatro estados reproducen las membresías reales de Nódico, tal y como
 * están en `docs/AUDITORIA-NODICO.md`. Las pruebas de cupo dependen de que
 * estos números sean los de verdad: si el catálogo cambia, cambia aquí.
 *
 * @extends Factory<Plane>
 */
class PlaneFactory extends Factory
{
    protected $model = Plane::class;

    public function definition(): array
    {
        return [
            'nombre'              => fake()->words(2, true),
            'tipo'                => 'mes',
            'precio'              => 599,
            'dias_cowork_mes'     => null,
            'horas_sala_mes'      => null,
            'horas_contenido_mes' => null,
            'max_horas_sala_dia'  => null,
            'personas'            => 1,
            'activo'              => true,
        ];
    }

    /** $79 · 1 día · 1 h de sala de contenido · sin salas privadas. */
    public function dayPass(): static
    {
        return $this->state(fn () => [
            'nombre'              => 'Day-Pass',
            'tipo'                => 'dia',
            'precio'              => 79,
            'dias_cowork_mes'     => 1,
            'horas_sala_mes'      => null,
            'horas_contenido_mes' => 1,
            'max_horas_sala_dia'  => null,
            'personas'            => 1,
        ]);
    }

    /** $249 · 4 días · 4 h de contenido (1 por día) · sin salas privadas. */
    public function flex(): static
    {
        return $this->state(fn () => [
            'nombre'              => 'Nódico Flex',
            'tipo'                => 'dia',
            'precio'              => 249,
            'dias_cowork_mes'     => 4,
            'horas_sala_mes'      => null,
            'horas_contenido_mes' => 4,
            'max_horas_sala_dia'  => null,
            'personas'            => 1,
        ]);
    }

    /** $599 · coworking ilimitado · 10 h de sala (máx. 2 h/día) · 10 h de contenido. */
    public function nodoPro(): static
    {
        return $this->state(fn () => [
            'nombre'              => 'Nodo Pro',
            'tipo'                => 'mes',
            'precio'              => 599,
            'dias_cowork_mes'     => null,
            'horas_sala_mes'      => 10,
            'horas_contenido_mes' => 10,
            'max_horas_sala_dia'  => 2,
            'personas'            => 1,
        ]);
    }

    /** $799 · 2 personas · 20 h de sala compartidas (máx. 2 h/día) · 15 h de contenido. */
    public function nodoMatch(): static
    {
        return $this->state(fn () => [
            'nombre'              => 'Nodo Match',
            'tipo'                => 'mes',
            'precio'              => 799,
            'dias_cowork_mes'     => null,
            'horas_sala_mes'      => 20,
            'horas_contenido_mes' => 15,
            'max_horas_sala_dia'  => 2,
            'personas'            => 2,
        ]);
    }
}
