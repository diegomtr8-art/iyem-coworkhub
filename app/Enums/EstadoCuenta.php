<?php

namespace App\Enums;

/**
 * Estado de la cuenta, **independiente del rol** (A.5).
 *
 * El rol dice que parte del sistema te toca; el estado dice cuanto puedes
 * hacer dentro de ella. Una membresia nace `pendiente` y solo pasa a `activa`
 * cuando se compra un plan o cuando recepcion la activa: hasta entonces la
 * persona puede ver su perfil y contratar, pero no reservar salas.
 *
 * Mezclarlo con el rol seria repetir el error de A.1: dos ejes distintos
 * comprimidos en una sola columna.
 */
enum EstadoCuenta: string
{
    case Pendiente  = 'pendiente';
    case Activa     = 'activa';
    case Suspendida = 'suspendida';

    /** Solo una cuenta activa consume el espacio: reservar, entrar, registrar horas. */
    public function puedeOperar(): bool
    {
        return $this === self::Activa;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente  => 'Pendiente de activación',
            self::Activa     => 'Activa',
            self::Suspendida => 'Suspendida',
        };
    }

    /** Texto que ve la persona en su portal. Explica que puede hacer, no solo como se llama. */
    public function descripcion(): string
    {
        return match ($this) {
            self::Pendiente  => 'Tu cuenta está creada pero todavía no tiene una membresía activa. Puedes completar tu perfil y contratar un plan; para reservar salas necesitas activarla.',
            self::Activa     => 'Tu membresía está activa. Tienes acceso completo al portal.',
            self::Suspendida => 'Tu cuenta está suspendida temporalmente. Escríbenos y lo revisamos contigo.',
        };
    }

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
