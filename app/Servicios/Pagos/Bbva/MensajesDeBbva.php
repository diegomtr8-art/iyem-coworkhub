<?php

namespace App\Servicios\Pagos\Bbva;

/**
 * Los errores de BBVA dichos en español claro y con algo que hacer.
 *
 * Códigos de https://docs.ecommercebbva.com/#c-digos-de-error. «Fondos
 * insuficientes» y «tarjeta reportada» no se le dicen igual a nadie, y ninguno
 * de los dos es «Error 3005». A una tarjeta reportada como robada no se le
 * acusa: se le pide otra forma de pago.
 */
final class MensajesDeBbva
{
    private const OTRA_TARJETA = 'Usa otra tarjeta o paga por referencia.';

    /** @var array<int, string> */
    private const POR_CODIGO = [
        // Tarjetas
        3001 => 'Tu banco rechazó el pago. Prueba con otra tarjeta o llama a tu banco.',
        3002 => 'Tu tarjeta está vencida. Usa otra.',
        3003 => 'La tarjeta no tiene saldo suficiente. Prueba con otra.',
        3004 => 'No pudimos cobrar con esta tarjeta. '.self::OTRA_TARJETA,
        3005 => 'No pudimos procesar esta tarjeta. '.self::OTRA_TARJETA,
        3006 => 'Esta tarjeta no permite este tipo de pago. Usa otra.',
        3008 => 'Tu tarjeta no está habilitada para compras en internet. Actívala en tu banco o usa otra.',
        3009 => 'No pudimos cobrar con esta tarjeta. '.self::OTRA_TARJETA,
        3010 => 'Tu banco bloqueó el pago. Llámalo antes de volver a intentarlo.',
        3011 => 'Tu banco bloqueó el pago. Llámalo antes de volver a intentarlo.',
        3012 => 'Tu banco pide que autorices este pago. Llámalo y vuelve a intentarlo.',
        // Datos de la tarjeta
        2004 => 'El número de tarjeta no es válido. Revísalo.',
        2005 => 'La fecha de vencimiento ya pasó. Revísala o usa otra tarjeta.',
        2006 => 'Falta el código de seguridad de la tarjeta.',
        2009 => 'El código de seguridad no es válido. Revísalo.',
    ];

    public static function paraCodigo(?int $codigo): string
    {
        if ($codigo !== null && isset(self::POR_CODIGO[$codigo])) {
            return self::POR_CODIGO[$codigo];
        }

        // Errores de integración (1xxx: llaves, formato, servicio caído) no
        // son culpa de la persona ni tiene nada que corregir: se registran y se
        // le ofrece la otra vía.
        return 'No pudimos iniciar el pago con tarjeta en este momento. Intenta de nuevo en unos minutos o paga por referencia.';
    }

    public static function paraError(ErrorDeBbva $e): string
    {
        return self::paraCodigo($e->codigo);
    }

    /**
     * Un cargo que BBVA marcó como fallido. En el cobro con formulario del
     * banco, lo que llega es `error_message` (texto): la documentación no
     * asegura que traiga el código numérico. Si lo trae, se traduce; si no,
     * mensaje general más lo que dijo el banco.
     */
    public static function paraCargoFallido(?string $mensajeDelBanco, ?int $codigo = null): string
    {
        if ($codigo !== null && isset(self::POR_CODIGO[$codigo])) {
            return self::POR_CODIGO[$codigo];
        }

        $base = 'Tu banco no aprobó el pago. Puedes intentarlo de nuevo con otra tarjeta o pagar por referencia.';

        return filled($mensajeDelBanco) ? $base.' (Respuesta del banco: '.trim($mensajeDelBanco).')' : $base;
    }
}
