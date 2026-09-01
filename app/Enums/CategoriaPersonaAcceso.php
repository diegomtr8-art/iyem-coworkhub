<?php

namespace App\Enums;

/**
 * Categoría de una persona con acceso que **no es miembro** de Nódico.
 *
 * Los miembros ya viven en `users`; esto cubre a quienes entran por el control
 * facial sin ser coworkers: el personal del IYEM y los prestadores de servicio
 * social. Se guardan en una sola tabla (`personas_acceso`) porque su ficha es
 * casi idéntica; la categoría es lo que separa los dos listados del panel.
 */
enum CategoriaPersonaAcceso: string
{
    case Empleado       = 'empleado';
    case ServicioSocial = 'servicio_social';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Empleado       => 'Empleado',
            self::ServicioSocial => 'Servicio social',
        };
    }

    /** Etiqueta en plural, para los títulos de listado. */
    public function plural(): string
    {
        return match ($this) {
            self::Empleado       => 'Empleados',
            self::ServicioSocial => 'Servicio social',
        };
    }

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
