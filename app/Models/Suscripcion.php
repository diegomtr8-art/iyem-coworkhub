<?php

namespace App\Models;

use App\Enums\BolsaDeHoras;
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

    public function user()      { return $this->belongsTo(User::class); }
    public function plan()      { return $this->belongsTo(Plane::class, 'plan_id'); }
    public function companion() { return $this->belongsTo(User::class, 'companion_user_id'); }
    public function reservas()  { return $this->hasMany(Reserva::class); }
    public function facturas()  { return $this->hasMany(Factura::class); }

    public function estaActiva(): bool
    {
        return $this->estatus === 'Activa' && ! today()->gt($this->fecha_fin);
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
