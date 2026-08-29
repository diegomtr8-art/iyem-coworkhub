<?php

namespace Database\Factories;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'tipo'              => RolUsuario::Miembro->value,
            'estado_cuenta'     => EstadoCuenta::Activa->value,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => RolUsuario::Admin->value]);
    }

    public function staff(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => RolUsuario::Staff->value]);
    }

    public function miembro(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => RolUsuario::Miembro->value]);
    }

    public function pendiente(): static
    {
        return $this->state(fn (array $attributes) => ['estado_cuenta' => EstadoCuenta::Pendiente->value]);
    }

    public function suspendida(): static
    {
        return $this->state(fn (array $attributes) => ['estado_cuenta' => EstadoCuenta::Suspendida->value]);
    }
}
