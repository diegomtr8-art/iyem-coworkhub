<?php

namespace App\Enums;

/**
 * B — Tipos de evento de la bitácora de autenticación.
 *
 * Enum y no cadenas sueltas por la misma razón que en A.3: un valor mal escrito
 * en un listener no debe convertirse en una fila que nadie vuelve a encontrar.
 * Los casos de las fases C y D ya están declarados para que los listeners de
 * esas fases no tengan que tocar la tabla ni la migración.
 */
enum EventoAuth: string
{
    case IngresoCorrecto  = 'ingreso_correcto';
    case IngresoFallido   = 'ingreso_fallido';
    case Bloqueo          = 'bloqueo';
    case CierreSesion     = 'cierre_sesion';
    case CierreRemoto     = 'cierre_remoto';
    case CorreoVerificado = 'correo_verificado';
    case CambioContrasena = 'cambio_contrasena';
    case RestablecioContrasena = 'restablecio_contrasena';
    case AltaDosFactores  = 'alta_2fa';
    case BajaDosFactores  = 'baja_2fa';
    case VinculoSocial    = 'vinculo_social';
    case DesvinculoSocial = 'desvinculo_social';

    public function etiqueta(): string
    {
        return match ($this) {
            self::IngresoCorrecto       => 'Ingreso correcto',
            self::IngresoFallido        => 'Ingreso fallido',
            self::Bloqueo               => 'Cuenta bloqueada por intentos',
            self::CierreSesion          => 'Cierre de sesión',
            self::CierreRemoto          => 'Cierre de sesión en otros dispositivos',
            self::CorreoVerificado      => 'Correo verificado',
            self::CambioContrasena      => 'Cambio de contraseña',
            self::RestablecioContrasena => 'Contraseña restablecida',
            self::AltaDosFactores       => 'Segundo factor activado',
            self::BajaDosFactores       => 'Segundo factor desactivado',
            self::VinculoSocial         => 'Cuenta externa vinculada',
            self::DesvinculoSocial      => 'Cuenta externa desvinculada',
        };
    }

    /** Eventos que conviene que salten a la vista en la bitácora. */
    public function esDelicado(): bool
    {
        return in_array($this, [
            self::Bloqueo,
            self::IngresoFallido,
            self::BajaDosFactores,
            self::CambioContrasena,
        ], true);
    }
}
