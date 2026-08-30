<?php

namespace App\Enums;

/**
 * Ciclo de vida de la renta de un salón (Fase 3.5).
 *
 * La distinción que importa: **una cotización no ocupa la agenda; una renta
 * confirmada sí.** Bloquear el salón al cotizar dejaría fechas muertas por
 * cada presupuesto que nadie aceptó, y no bloquearlo al confirmar es vender la
 * misma fecha dos veces.
 */
enum EstadoRentaSalon: string
{
    /** Presupuesto enviado. No aparta la fecha. */
    case Cotizacion = 'cotizacion';

    /** Aceptada y con la fecha apartada: genera un bloqueo en la agenda. */
    case Confirmada = 'confirmada';

    /** El evento ya ocurrió. */
    case Realizada = 'realizada';

    case Cancelada = 'cancelada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Cotizacion => 'Cotización',
            self::Confirmada => 'Confirmada',
            self::Realizada  => 'Realizada',
            self::Cancelada  => 'Cancelada',
        };
    }

    /** Si en este estado el salón está apartado en la agenda. */
    public function ocupaAgenda(): bool
    {
        return in_array($this, [self::Confirmada, self::Realizada], true);
    }

    public function esFinal(): bool
    {
        return in_array($this, [self::Realizada, self::Cancelada], true);
    }

    /** Tono semántico del panel. Separado del amarillo de marca a propósito. */
    public function tono(): string
    {
        return match ($this) {
            self::Cotizacion => 'atencion',
            self::Confirmada => 'bien',
            self::Realizada  => 'neutro',
            self::Cancelada  => 'problema',
        };
    }

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
