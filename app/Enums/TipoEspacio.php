<?php

namespace App\Enums;

/**
 * Catálogo de espacios de Nódico (BUG-06).
 *
 * Antes había dos catálogos que no se hablaban: la columna `tipo` guardaba
 * `privado`, `contenido`, `fotografia` y `coworking`, mientras
 * `Espacio::getTipoLabelAttribute()` solo conocía `escritorio`,
 * `oficina_privada`, `cabina_telefonica` y `lounge`. Los cuatro tipos con los
 * que opera el negocio se le enseñaban al usuario **en crudo y en minúsculas**,
 * y `privado` / `oficina_privada` eran el mismo espacio con dos nombres.
 *
 * Aquí vive el catálogo entero: etiqueta, bolsa que consume y si se reserva.
 * Añadir un tipo es añadir un `case`, no tocar cinco `match` repartidos.
 */
enum TipoEspacio: string
{
    case Coworking    = 'coworking';
    case Privado      = 'privado';
    case SalaJuntas   = 'sala_juntas';
    case Contenido    = 'contenido';
    case Fotografia   = 'fotografia';
    case SalonEventos = 'salon_eventos';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Coworking    => 'Área de coworking',
            self::Privado      => 'Oficina privada',
            self::SalaJuntas   => 'Sala de juntas',
            self::Contenido    => 'Sala de creación de contenido',
            self::Fotografia   => 'Sala de fotografía',
            self::SalonEventos => 'Salón para eventos',
        };
    }

    /**
     * Bolsa de horas que consume reservar este espacio, o `null` si no consume
     * ninguna.
     *
     * Es **la** fuente de verdad del BUG-02: qué bolsa toca cada espacio se
     * decide aquí y en ningún otro sitio.
     */
    public function bolsa(): ?BolsaDeHoras
    {
        return match ($this) {
            self::Privado, self::SalaJuntas   => BolsaDeHoras::Sala,
            self::Contenido, self::Fotografia => BolsaDeHoras::Contenido,
            self::Coworking, self::SalonEventos => null,
        };
    }

    /**
     * Si el miembro puede reservarlo desde el portal.
     *
     * El **coworking no se reserva** (decisión de Nódico, 29/08/2026): es de
     * acceso libre y lo que cuenta es el check-in, que es además lo que consume
     * días en Day-Pass y Flex.
     *
     * Los **salones** tampoco: se cotizan aparte, los contrata gente que no es
     * miembro y los agenda el operativo (Fase 3.5).
     */
    public function reservablePorMiembro(): bool
    {
        return $this->bolsa() !== null;
    }

    /** Tipos que el miembro ve en el selector de reserva. */
    public static function reservables(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $tipo) => $tipo->reservablePorMiembro()
        ));
    }

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Tipos que existieron en el esquema original y ya no se usan, con el tipo
     * vivo al que se migran. Lo consume la migración de consolidación.
     *
     * @return array<string, string>
     */
    public static function equivalenciasHistoricas(): array
    {
        return [
            'oficina_privada'   => self::Privado->value,
            'cabina_telefonica' => self::Privado->value,
            'escritorio'        => self::Coworking->value,
            'lounge'            => self::Coworking->value,
        ];
    }
}
