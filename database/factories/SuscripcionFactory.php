<?php

namespace Database\Factories;

use App\Models\Plane;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suscripcion>
 */
class SuscripcionFactory extends Factory
{
    protected $model = Suscripcion::class;

    public function definition(): array
    {
        return [
            'user_id'                => User::factory(),
            'plan_id'                => Plane::factory(),
            'fecha_inicio'           => today(),
            'fecha_fin'              => today()->addMonth(),
            'estatus'                => 'Activa',
            'precio_pagado'          => 599,
            'auto_renovar'           => false,
            'horas_sala_usadas'      => 0,
            'horas_contenido_usadas' => 0,
            'dias_usados'            => 0,
        ];
    }

    public function vencida(): static
    {
        return $this->state(fn () => [
            'estatus'      => 'Vencida',
            'fecha_inicio' => today()->subMonths(2),
            'fecha_fin'    => today()->subMonth(),
        ]);
    }

    public function delPlan(Plane $plan): static
    {
        return $this->state(fn () => [
            'plan_id'       => $plan->id,
            'precio_pagado' => $plan->precio,
        ]);
    }
}
