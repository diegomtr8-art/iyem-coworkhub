<?php

namespace Database\Factories;

use App\Models\Espacio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Espacio>
 */
class EspacioFactory extends Factory
{
    protected $model = Espacio::class;

    public function definition(): array
    {
        return [
            'nombre'      => fake()->words(2, true),
            'tipo'        => 'sala_juntas',
            'capacidad'   => 8,
            'precio_hora' => 0,
            'disponible'  => true,
            'piso'        => 1,
        ];
    }

    public function salaJuntas(): static
    {
        return $this->state(fn () => ['tipo' => 'sala_juntas', 'nombre' => 'Sala de juntas']);
    }

    public function privado(): static
    {
        return $this->state(fn () => ['tipo' => 'privado', 'nombre' => 'Oficina privada', 'capacidad' => 4]);
    }

    public function contenido(): static
    {
        return $this->state(fn () => ['tipo' => 'contenido', 'nombre' => 'Sala de creación de contenido', 'capacidad' => 3]);
    }

    public function fotografia(): static
    {
        return $this->state(fn () => ['tipo' => 'fotografia', 'nombre' => 'Sala de fotografía', 'capacidad' => 3]);
    }

    public function coworking(): static
    {
        return $this->state(fn () => ['tipo' => 'coworking', 'nombre' => 'Área de coworking', 'capacidad' => 40]);
    }

    public function salon(): static
    {
        return $this->state(fn () => [
            'tipo'          => 'salon_eventos',
            'nombre'        => 'Yucatán Emprende 1',
            'capacidad'     => 120,
            'precio_hora'   => 600,
            'cap_herradura' => 45,
            'cap_mesas'     => 70,
            'cap_escuela'   => 54,
            'cap_auditorio' => 120,
        ]);
    }
}
