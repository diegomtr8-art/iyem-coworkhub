<?php

namespace App\Enums;

/**
 * Ciclo del **pago** de una orden con referencia.
 *
 * Separado a propósito del ciclo de la factura ([[EstadoFacturaOrden]]): alguien
 * puede tener el pago confirmado y la factura pendiente durante días. Meterlos en
 * una sola columna borraría ese estado y contabilidad perdería la pista.
 *
 *   generada → confirmada · vencida · cancelada
 */
enum EstadoPagoOrden: string
{
    case Generada   = 'generada';
    case Confirmada = 'confirmada';
    case Vencida    = 'vencida';
    case Cancelada  = 'cancelada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Generada   => 'Pendiente de pago',
            self::Confirmada => 'Pago confirmado',
            self::Vencida    => 'Vencida',
            self::Cancelada  => 'Cancelada',
        };
    }

    /** Una orden viva es la que todavía se puede pagar y confirmar. */
    public function estaAbierta(): bool
    {
        return $this === self::Generada;
    }
}
