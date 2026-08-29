<?php

namespace App\Models;

use App\Enums\BolsaDeHoras;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * La membresía contratada por un miembro.
 *
 * Los campos `horas_*_usadas` y `dias_usados` son **caché del consumo del ciclo
 * en curso**, no el dato de origen. Quien necesite un saldo debe pedirlo a
 * `BolsaDeHoras`, que ya aplica la semántica de `null` del plan (ver `Plane`).
 */
class Suscripcion extends Model
{
    use HasFactory;

    protected $table = 'suscripciones';

    protected $fillable = [
        'user_id', 'plan_id', 'fecha_inicio', 'fecha_fin',
        'estatus', 'precio_pagado', 'auto_renovar',
        'horas_sala_usadas', 'horas_contenido_usadas', 'horas_asesoria_usadas', 'dias_usados',
        'companion_user_id', 'companion_face_id_ok',
    ];

    protected $casts = [
        'fecha_inicio'           => 'date',
        'fecha_fin'              => 'date',
        'auto_renovar'           => 'boolean',
        'companion_face_id_ok'   => 'boolean',
        'horas_sala_usadas'      => 'float',
        'horas_contenido_usadas' => 'float',
        'horas_asesoria_usadas'  => 'float',
        'precio_pagado'          => 'decimal:2',
    ];

    public function user()        { return $this->belongsTo(User::class); }
    public function plan()        { return $this->belongsTo(Plane::class, 'plan_id'); }
    public function companion()   { return $this->belongsTo(User::class, 'companion_user_id'); }
    public function reservas()    { return $this->hasMany(Reserva::class); }
    public function facturas()    { return $this->hasMany(Factura::class); }
    public function movimientos() { return $this->hasMany(MovimientoHoras::class); }
    public function asesorias()   { return $this->hasMany(SolicitudAsesoria::class); }

    public function estaActiva(): bool
    {
        return $this->estatus === 'Activa' && ! today()->gt($this->fecha_fin);
    }

    // ── Ciclo de aniversario ────────────────────────────────────────────────

    /**
     * Inicio del ciclo vigente en una fecha dada.
     *
     * Decisión de Nódico: **el ciclo va por aniversario de la suscripción**,
     * no por mes natural. Quien contrata el 15 de marzo tiene sus horas del 15
     * de marzo al 14 de abril. Sin acumulación: lo no usado se pierde.
     *
     * Se usa `addMonthsNoOverflow` a propósito. Con `addMonths`, una
     * suscripción del 31 de enero saltaría al 3 de marzo —febrero no tiene 31—
     * y el miembro perdería tres días de ciclo cada año. Con `NoOverflow` cae
     * en el 28 o 29 de febrero, que es lo que cualquiera entiende por «el mismo
     * día del mes siguiente».
     *
     * Los planes por día (Day-Pass, Flex) no tienen ciclos: su ciclo es la
     * suscripción entera.
     */
    public function cicloInicio(?CarbonImmutable $en = null): CarbonImmutable
    {
        $en     = $en ?? CarbonImmutable::today();
        $inicio = CarbonImmutable::parse($this->fecha_inicio)->startOfDay();

        if (! ($this->plan?->tieneCiclosMensuales() ?? false)) {
            return $inicio;
        }

        if ($en->lte($inicio)) {
            return $inicio;
        }

        // Los meses se cuentan **por calendario**, no con `diffInMonths`.
        //
        // `diffInMonths` cuenta meses *cumplidos*, y del 31 de enero al 28 de
        // febrero devuelve 0: para Carbon aún no ha pasado un mes, porque el
        // 31 de febrero no existe y el mes se cumpliría el 3 de marzo. Con eso,
        // una suscripción de fin de mes se quedaría sin reiniciar en febrero.
        //
        // La diferencia de año y mes no tiene ese problema, y el ajuste de
        // abajo corrige el único caso que deja suelto: que el aniversario de
        // este mes todavía no haya llegado.
        $meses     = ($en->year - $inicio->year) * 12 + ($en->month - $inicio->month);
        $candidato = $inicio->addMonthsNoOverflow($meses);

        if ($candidato->gt($en)) {
            $candidato = $inicio->addMonthsNoOverflow(max(0, $meses - 1));
        }

        return $candidato;
    }

    /** Último día del ciclo vigente: la víspera del siguiente aniversario. */
    public function cicloFin(?CarbonImmutable $en = null): CarbonImmutable
    {
        if (! ($this->plan?->tieneCiclosMensuales() ?? false)) {
            return CarbonImmutable::parse($this->fecha_fin)->startOfDay();
        }

        $siguiente = $this->cicloInicio($en)->addMonthNoOverflow();

        // El ciclo nunca sobrevive a la membresía.
        $fin = CarbonImmutable::parse($this->fecha_fin)->startOfDay();

        return $siguiente->subDay()->min($fin);
    }

    /** Cuándo se reinician las bolsas. Es lo que el portal enseña junto a cada medidor. */
    public function proximoReinicio(?CarbonImmutable $en = null): ?CarbonImmutable
    {
        if (! ($this->plan?->tieneCiclosMensuales() ?? false)) {
            return null;
        }

        return $this->cicloFin($en)->addDay();
    }

    public function getDiasRestantesAttribute(): int
    {
        return max(0, (int) today()->diffInDays($this->fecha_fin, false));
    }

    // ── Saldos ──────────────────────────────────────────────────────────────

    /**
     * Saldo de una bolsa. `null` = **ilimitada**, y solo la de días puede serlo.
     *
     * Los métodos de abajo se conservan porque las vistas existentes los usan,
     * pero delegan aquí: la aritmética de saldos vive en un solo sitio.
     */
    public function restanteDe(BolsaDeHoras $bolsa): ?float
    {
        return $bolsa->restante($this);
    }

    public function horasSalaRestantes(): float
    {
        return (float) $this->restanteDe(BolsaDeHoras::Sala);
    }

    public function horasContenidoRestantes(): float
    {
        return (float) $this->restanteDe(BolsaDeHoras::Contenido);
    }

    public function horasAsesoriaRestantes(): float
    {
        return (float) $this->restanteDe(BolsaDeHoras::Asesoria);
    }

    /** Días de coworking restantes, o `null` si el plan es ilimitado. */
    public function diasRestantesMes(): ?int
    {
        $restante = $this->restanteDe(BolsaDeHoras::Dias);

        return is_null($restante) ? null : (int) $restante;
    }
}
