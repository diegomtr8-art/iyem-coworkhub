<?php

namespace App\Enums;

/**
 * Ciclo de la **factura** de una orden con referencia.
 *
 * Nódico no timbra CFDI: contabilidad del IYEM emite la factura en su sistema y
 * sube el PDF y el XML. Este ciclo va aparte del pago ([[EstadoPagoOrden]]).
 *
 *   no_solicitada · solicitada → emitida → enviada
 */
enum EstadoFacturaOrden: string
{
    case NoSolicitada = 'no_solicitada';
    case Solicitada   = 'solicitada';
    case Emitida      = 'emitida';
    case Enviada      = 'enviada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::NoSolicitada => 'Sin factura',
            self::Solicitada   => 'Factura pendiente',
            self::Emitida      => 'Factura emitida',
            self::Enviada      => 'Factura enviada',
        };
    }
}
