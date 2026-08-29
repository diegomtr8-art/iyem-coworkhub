<?php

namespace App\Models;

use App\Enums\BolsaDeHoras;
use App\Enums\MotivoMovimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un apunte del libro de horas (Fase 1.1).
 *
 * `cantidad` va **con signo: positiva consume, negativa devuelve**. El consumo
 * de un ciclo es la suma de sus movimientos, y el saldo, el cupo del plan menos
 * esa suma.
 *
 * Los movimientos no se editan ni se borran nunca: una corrección es otro
 * movimiento, con su motivo y su nota. Eso es lo que hace que el libro sirva
 * de auditoría.
 */
class MovimientoHoras extends Model
{
    use HasFactory;

    protected $table = 'movimientos_horas';

    protected $fillable = [
        'suscripcion_id', 'bolsa', 'cantidad', 'motivo', 'ciclo_inicio',
        'reserva_id', 'solicitud_asesoria_id', 'creado_por_user_id', 'nota', 'clave_idempotencia',
    ];

    protected $casts = [
        'cantidad'     => 'float',
        'ciclo_inicio' => 'date',
        'bolsa'        => BolsaDeHoras::class,
        'motivo'       => MotivoMovimiento::class,
    ];

    public function suscripcion() { return $this->belongsTo(Suscripcion::class); }
    public function reserva()     { return $this->belongsTo(Reserva::class); }
    public function solicitud()   { return $this->belongsTo(SolicitudAsesoria::class, 'solicitud_asesoria_id'); }
    public function creadoPor()   { return $this->belongsTo(User::class, 'creado_por_user_id'); }

    public function scopeDeBolsa(Builder $query, BolsaDeHoras $bolsa): Builder
    {
        return $query->where('bolsa', $bolsa->value);
    }

    public function scopeDelCiclo(Builder $query, string $cicloInicio): Builder
    {
        return $query->whereDate('ciclo_inicio', $cicloInicio);
    }

    public function consume(): bool
    {
        return $this->cantidad > 0;
    }

    public function devuelve(): bool
    {
        return $this->cantidad < 0;
    }

    /** Texto para el historial del miembro: «−2 h · Reserva» o «+2 h · Cancelación». */
    public function descripcion(): string
    {
        $signo    = $this->consume() ? '−' : '+';
        $cantidad = rtrim(rtrim(number_format(abs($this->cantidad), 2, '.', ''), '0'), '.');
        $unidad   = $this->bolsa->unidad() === 'días'
            ? (abs($this->cantidad) == 1 ? 'día' : 'días')
            : (abs($this->cantidad) == 1 ? 'hora' : 'horas');

        return "{$signo}{$cantidad} {$unidad} · " . $this->motivo->etiqueta();
    }
}
