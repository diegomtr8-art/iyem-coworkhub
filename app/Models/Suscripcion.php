<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Suscripcion extends Model
{
    protected $table = 'suscripciones';

    protected $fillable = [
        'user_id', 'plan_id', 'fecha_inicio', 'fecha_fin',
        'estatus', 'precio_pagado', 'auto_renovar',
        'horas_sala_usadas', 'horas_contenido_usadas', 'dias_usados',
        'companion_user_id', 'companion_face_id_ok',
    ];

    protected $casts = [
        'fecha_inicio'           => 'date',
        'fecha_fin'              => 'date',
        'auto_renovar'           => 'boolean',
        'companion_face_id_ok'   => 'boolean',
        'horas_sala_usadas'      => 'float',
        'horas_contenido_usadas' => 'float',
        'precio_pagado'          => 'decimal:2',
    ];

    public function user()      { return $this->belongsTo(User::class); }
    public function plan()      { return $this->belongsTo(Plane::class, 'plan_id'); }
    public function companion() { return $this->belongsTo(User::class, 'companion_user_id'); }
    public function reservas()  { return $this->hasMany(Reserva::class); }
    public function facturas()  { return $this->hasMany(Factura::class); }

    public function getDiasRestantesAttribute(): int
    {
        return max(0, now()->diffInDays($this->fecha_fin, false));
    }

    public function horasSalaRestantes(): float
    {
        $max = $this->plan?->horas_sala_mes ?? 0;
        return max(0, $max - ($this->horas_sala_usadas ?? 0));
    }

    public function horasContenidoRestantes(): float
    {
        $max = $this->plan?->horas_contenido_mes ?? 0;
        return max(0, $max - ($this->horas_contenido_usadas ?? 0));
    }

    public function diasRestantesMes(): ?int
    {
        if (is_null($this->plan?->dias_cowork_mes)) return null;
        return max(0, $this->plan->dias_cowork_mes - ($this->dias_usados ?? 0));
    }
}
