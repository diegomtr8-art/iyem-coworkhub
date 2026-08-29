<?php

namespace App\Enums;

/**
 * Por qué se movió una bolsa de horas.
 *
 * Un contador suelto no se puede auditar: no explica de dónde salió el saldo,
 * no se puede corregir con constancia y esconde los errores durante meses
 * —el BUG-01 llevaba justo eso—. Cada movimiento del libro lleva su motivo,
 * y con el motivo se sabe si el saldo bajó porque alguien reservó, porque no
 * se presentó, o porque recepción se lo repuso a mano y con nota.
 */
enum MotivoMovimiento: string
{
    /** Consumo al confirmar una reserva. */
    case Reserva = 'reserva';

    /** Devolución al cancelar a tiempo (2 h o más de antelación). */
    case Cancelacion = 'cancelacion';

    /** Recepción repone o descuenta horas. Exige nota y usuario. */
    case AjusteManual = 'ajuste_manual';

    /**
     * El miembro no se presentó. **No devuelve horas**: es lo que hace que la
     * regla de las 2 horas signifique algo.
     */
    case NoShow = 'no_show';

    /** Arranque de un ciclo de aniversario. Cantidad 0: es un marcador. */
    case ReinicioCiclo = 'reinicio_ciclo';

    /** Consumo al confirmar una solicitud de asesoría IYEM. */
    case Asesoria = 'asesoria';

    /** Devolución al rechazar o cancelar a tiempo una asesoría. */
    case CancelacionAsesoria = 'cancelacion_asesoria';

    /** Día consumido al hacer check-in. */
    case Acceso = 'acceso';

    /**
     * Saldo de arranque al pasar los contadores viejos al libro. Solo lo
     * escribe `nodico:sembrar-libro-horas`, una vez.
     */
    case SaldoInicial = 'saldo_inicial';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Reserva             => 'Reserva',
            self::Cancelacion         => 'Cancelación',
            self::AjusteManual        => 'Ajuste manual',
            self::NoShow              => 'No se presentó',
            self::ReinicioCiclo       => 'Reinicio de ciclo',
            self::Asesoria            => 'Asesoría IYEM',
            self::CancelacionAsesoria => 'Asesoría cancelada',
            self::Acceso              => 'Acceso al espacio',
            self::SaldoInicial        => 'Saldo inicial',
        };
    }

    /** Si el motivo exige una nota escrita por quien lo originó. */
    public function exigeNota(): bool
    {
        return $this === self::AjusteManual;
    }

    /** Si lo puede originar una persona desde el panel, o solo el sistema. */
    public function esManual(): bool
    {
        return $this === self::AjusteManual;
    }

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
