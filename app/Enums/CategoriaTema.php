<?php

namespace App\Enums;

/**
 * Fase 4.D — las dos categorías de la oferta de asesoría IYEM.
 *
 * «Básicos» son los temas de arranque que casi todo emprendedor necesita;
 * «especializados», los de quien ya va más avanzado. La distinción la usa el
 * portal para agrupar la oferta.
 */
enum CategoriaTema: string
{
    case Basicos        = 'basicos';
    case Especializados = 'especializados';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Basicos        => 'Básicos',
            self::Especializados => 'Especializados',
        };
    }

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
