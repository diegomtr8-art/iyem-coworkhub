<?php

namespace App\Enums;

use App\Models\Espacio;

/**
 * Cómo se acomoda un salón, y cuánta gente cabe en cada acomodo (Fase 3.5).
 *
 * La capacidad **no es una sola cifra**: Yucatán Emprende 1 admite 120 personas
 * en auditorio y 45 en herradura. Cotizar sin saberlo es prometer un aforo que
 * no existe, y eso se descubre el día del evento.
 *
 * Los números salen de las columnas `cap_*` de `espacios`, sembradas desde la
 * ficha real de cada salón (ver `docs/AUDITORIA-NODICO.md`).
 */
enum TipoMontaje: string
{
    case Herradura  = 'herradura';
    case Escuela    = 'escuela';
    case MesasTrabajo = 'mesas_trabajo';
    case Auditorio  = 'auditorio';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Herradura    => 'Herradura',
            self::Escuela      => 'Escuela',
            self::MesasTrabajo => 'Mesas de trabajo',
            self::Auditorio    => 'Auditorio',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Herradura    => 'Mesas en U, todos se ven entre sí. Para juntas de consejo y talleres pequeños.',
            self::Escuela      => 'Filas de mesas mirando al frente. Para capacitaciones donde hay que escribir.',
            self::MesasTrabajo => 'Mesas redondas o en isla. Para dinámicas por equipos.',
            self::Auditorio    => 'Solo sillas en filas. El que más gente admite.',
        };
    }

    /** Columna de `espacios` con el aforo de este montaje. */
    public function campoCapacidad(): string
    {
        return match ($this) {
            self::Herradura    => 'cap_herradura',
            self::Escuela      => 'cap_escuela',
            self::MesasTrabajo => 'cap_mesas',
            self::Auditorio    => 'cap_auditorio',
        };
    }

    /** Aforo del salón en este montaje, o `null` si no está capturado. */
    public function capacidadEn(?Espacio $espacio): ?int
    {
        $valor = $espacio?->{$this->campoCapacidad()};

        return is_null($valor) ? null : (int) $valor;
    }

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
