<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Espacio extends Model
{
    protected $fillable = [
        'nombre', 'tipo', 'capacidad', 'precio_hora', 'amenidades', 'disponible', 'piso',
    ];

    protected $casts = [
        'amenidades' => 'array',
        'disponible' => 'boolean',
        'precio_hora' => 'decimal:2',
    ];

    public function reservas() { return $this->hasMany(Reserva::class); }
    public function checkins() { return $this->hasMany(Checkin::class); }

    public function getTipoLabelAttribute(): string
    {
        return match($this->tipo) {
            'escritorio' => 'Escritorio',
            'oficina_privada' => 'Oficina Privada',
            'sala_juntas' => 'Sala de Juntas',
            'cabina_telefonica' => 'Cabina Telefónica',
            'lounge' => 'Lounge',
            default => $this->tipo,
        };
    }
}
