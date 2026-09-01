<?php

namespace Database\Factories;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Models\Consentimiento;
use App\Models\User;
use App\Support\DocumentosLegales;
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

    /**
     * E — Una cuenta creada por la factory nace con el consentimiento al dia.
     *
     * Es lo que refleja la realidad: nadie llega a existir sin haber aceptado,
     * porque el registro lo exige. Sin esto, cada prueba que entra al portal
     * tendria que registrar la constancia a mano, y el ruido escondería lo que
     * de verdad prueba.
     *
     * Para lo contrario esta `sinConsentimiento()`.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $usuario) {
            foreach (app(DocumentosLegales::class)->versiones() as $documento => $version) {
                Consentimiento::create([
                    'user_id'     => $usuario->id,
                    'documento'   => $documento,
                    'version'     => $version,
                    'aceptado_en' => now(),
                    'ip'          => '127.0.0.1',
                ]);
            }
        });
    }

    /** Cuenta que todavia no ha aceptado nada. */
    public function sinConsentimiento(): static
    {
        return $this->afterCreating(fn (User $usuario) => $usuario->consentimientos()->delete());
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

    public function caja(): static
    {
        return $this->state(fn (array $attributes) => ['tipo' => RolUsuario::Caja->value]);
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
