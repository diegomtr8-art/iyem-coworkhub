<?php

namespace App\Servicios\Pagos;

use App\Models\Plane;
use InvalidArgumentException;

/**
 * El importe que se manda a BBVA. **El único lugar** donde se calcula.
 *
 * BBVA cobra en **pesos con hasta dos decimales**: «Cantidad del cargo. Debe ser
 * una cantidad mayor a cero, con hasta dos dígitos decimales»
 * (https://docs.ecommercebbva.com/#con-vpos). `"amount": 599` son quinientos
 * noventa y nueve pesos.
 *
 * Stripe, en cambio, cobra en centavos enteros (`precio * 100`). Si ese
 * cálculo se colara aquí, Nodo Pro se cobraría $59,900: el error más caro
 * posible de toda la migración. Por eso vive aparte, con su prueba
 * (`ImporteBbvaTest`) y un tope de cordura.
 */
final class Importe
{
    /** Nada de Nódico cuesta más que esto en un solo cargo. */
    private const TOPE_PESOS = 50_000;

    public static function enPesos(Plane $plan): float
    {
        // `planes.precio` es decimal(10,2); el modelo lo convierte a float. El
        // redondeo evita mandar arrastres de coma flotante (79.00000000001).
        $pesos = round((float) $plan->precio, 2);

        if ($pesos <= 0 || $pesos > self::TOPE_PESOS) {
            throw new InvalidArgumentException(
                "Importe fuera de rango para el plan {$plan->id}: {$pesos}. ¿Se mandaron centavos en vez de pesos?"
            );
        }

        return $pesos;
    }

    /** Dos importes en pesos son el mismo (tolerancia de medio centavo). */
    public static function iguales(float $a, float $b): bool
    {
        return abs(round($a, 2) - round($b, 2)) < 0.005;
    }
}
