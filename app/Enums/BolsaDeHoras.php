<?php

namespace App\Enums;

use App\Models\Plane;
use App\Models\Suscripcion;

/**
 * Las bolsas de consumo de una suscripción.
 *
 * Cada bolsa sabe de qué campo del plan sale su cupo, en qué contador de la
 * suscripción se cachea y cuál es su tope diario. Esa correspondencia estaba
 * antes repetida a mano en el controlador, en el modelo y en las dos vistas,
 * que es como `incluye_sala_juntas` y `horas_sala_mes` acabaron gobernando la
 * misma regla sin coincidir (BUG-02).
 */
enum BolsaDeHoras: string
{
    case Sala      = 'sala';
    case Contenido = 'contenido';
    case Asesoria  = 'asesoria';
    case Dias      = 'dias';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Sala      => 'Salas privadas y de juntas',
            self::Contenido => 'Sala de creación de contenido',
            self::Asesoria  => 'Asesoría IYEM',
            self::Dias      => 'Días de coworking',
        };
    }

    public function unidad(): string
    {
        return $this === self::Dias ? 'días' : 'horas';
    }

    /** Campo de `planes` con el cupo del ciclo. `null` en ese campo = sin acceso. */
    public function campoCupo(): string
    {
        return match ($this) {
            self::Sala      => 'horas_sala_mes',
            self::Contenido => 'horas_contenido_mes',
            self::Asesoria  => 'horas_asesoria_mes',
            self::Dias      => 'dias_cowork_mes',
        };
    }

    /** Campo de `suscripciones` donde se cachea el consumo del ciclo. */
    public function campoConsumo(): string
    {
        return match ($this) {
            self::Sala      => 'horas_sala_usadas',
            self::Contenido => 'horas_contenido_usadas',
            self::Asesoria  => 'horas_asesoria_usadas',
            self::Dias      => 'dias_usados',
        };
    }

    /** Campo de `planes` con el tope por día, o `null` si la bolsa no tiene tope diario. */
    public function campoTopeDiario(): ?string
    {
        return match ($this) {
            self::Sala      => 'max_horas_sala_dia',
            self::Contenido => 'max_horas_contenido_dia',
            self::Asesoria  => 'max_horas_asesoria_dia',
            self::Dias      => null,
        };
    }

    /**
     * Cupo del ciclo para este plan.
     *
     * `null` significa cosas distintas según la bolsa, y por eso se resuelve
     * aquí y no en cada sitio que lo consulta:
     *
     * - en `dias_cowork_mes`, `null` = **coworking ilimitado**;
     * - en las bolsas de horas, `null` = **el plan no incluye esa bolsa**.
     */
    public function cupo(?Plane $plan): ?float
    {
        $valor = $plan?->{$this->campoCupo()};

        return is_null($valor) ? null : (float) $valor;
    }

    /** Tope por día de este plan, o `null` si no lo tiene. */
    public function topeDiario(?Plane $plan): ?float
    {
        $campo = $this->campoTopeDiario();

        if ($campo === null) {
            return null;
        }

        $valor = $plan?->{$campo};

        return is_null($valor) ? null : (float) $valor;
    }

    /** Si el plan da acceso a esta bolsa. En `Dias`, ilimitado también es acceso. */
    public function incluidaEn(?Plane $plan): bool
    {
        if ($this === self::Dias) {
            return $plan !== null;
        }

        return $this->cupo($plan) !== null;
    }

    /** Consumo cacheado del ciclo en curso. */
    public function consumo(Suscripcion $suscripcion): float
    {
        return (float) ($suscripcion->{$this->campoConsumo()} ?? 0);
    }

    /**
     * Saldo restante, o `null` cuando la bolsa es ilimitada (solo `Dias`).
     *
     * Ojo: `null` aquí es **ilimitado**, nunca «sin acceso». Para eso está
     * `incluidaEn()`. Esa ambigüedad fue justo la del BUG-02.
     */
    public function restante(Suscripcion $suscripcion): ?float
    {
        $cupo = $this->cupo($suscripcion->plan);

        if ($cupo === null) {
            return $this === self::Dias ? null : 0.0;
        }

        return max(0.0, $cupo - $this->consumo($suscripcion));
    }
}
