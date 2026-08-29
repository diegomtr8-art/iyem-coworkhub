<?php

namespace Database\Factories;

use App\Models\Espacio;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reserva>
 */
class ReservaFactory extends Factory
{
    protected $model = Reserva::class;

    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'espacio_id'   => Espacio::factory(),
            'fecha'        => today(),
            'hora_inicio'  => '09:00:00',
            'hora_fin'     => '11:00:00',
            'estatus'      => 'Confirmada',
            'precio_total' => 0,
        ];
    }

    public function cancelada(): static
    {
        return $this->state(fn () => ['estatus' => 'Cancelada']);
    }
}
