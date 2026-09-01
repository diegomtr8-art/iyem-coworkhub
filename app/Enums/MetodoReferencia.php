<?php

namespace App\Enums;

/**
 * Cómo se paga una orden con referencia (Fase de pagos con referencia).
 *
 * Es la ruta que **sí genera factura** (a diferencia de la tarjeta por Stripe,
 * que activa al instante pero no factura). Ambos métodos generan una referencia
 * y esperan la confirmación de contabilidad; lo que cambia son las instrucciones.
 */
enum MetodoReferencia: string
{
    case Transferencia = 'transferencia';
    case Efectivo      = 'efectivo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Transferencia => 'Transferencia',
            self::Efectivo      => 'Efectivo',
        };
    }
}
