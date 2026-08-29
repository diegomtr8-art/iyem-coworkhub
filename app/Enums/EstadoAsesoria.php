<?php

namespace App\Enums;

/**
 * Ciclo de vida de una solicitud de asesoría IYEM.
 *
 * Decisión de Nódico: **el miembro pide, el operativo confirma.** No hay
 * agendas de asesores por ahora; el miembro dice qué día y en qué franja le
 * viene bien, y recepción asigna asesor y confirma.
 *
 * Las horas se descuentan **al confirmar**, no al solicitar. Pedir no cuesta:
 * si el operativo rechaza o el miembro se echa atrás a tiempo, la bolsa queda
 * intacta.
 */
enum EstadoAsesoria: string
{
    case Solicitada = 'solicitada';
    case Confirmada = 'confirmada';
    case Realizada  = 'realizada';
    case Cancelada  = 'cancelada';
    case Rechazada  = 'rechazada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Solicitada => 'Solicitada',
            self::Confirmada => 'Confirmada',
            self::Realizada  => 'Realizada',
            self::Cancelada  => 'Cancelada',
            self::Rechazada  => 'Rechazada',
        };
    }

    /** Si en este estado las horas están descontadas de la bolsa. */
    public function consumeBolsa(): bool
    {
        return in_array($this, [self::Confirmada, self::Realizada], true);
    }

    /** Si el operativo todavía tiene que hacer algo con ella. */
    public function estaPendiente(): bool
    {
        return $this === self::Solicitada;
    }

    /** Si ya no admite más cambios. */
    public function esFinal(): bool
    {
        return in_array($this, [self::Realizada, self::Cancelada, self::Rechazada], true);
    }

    /**
     * Color semántico para el panel operativo. A propósito **fuera** del
     * amarillo de marca: en una herramienta, el estado tiene que leerse como
     * estado y no confundirse con el acento de Nódico.
     */
    public function tono(): string
    {
        return match ($this) {
            self::Solicitada => 'atencion',
            self::Confirmada => 'bien',
            self::Realizada  => 'neutro',
            self::Cancelada, self::Rechazada => 'problema',
        };
    }

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
