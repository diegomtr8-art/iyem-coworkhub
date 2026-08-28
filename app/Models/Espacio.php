<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Espacio extends Model
{
    protected $fillable = [
        'nombre', 'tipo', 'capacidad', 'precio_hora', 'amenidades', 'disponible', 'piso',
        'descripcion', 'medidas', 'incluye', 'cap_herradura', 'cap_mesas',
        'cap_escuela', 'cap_auditorio', 'imagen', 'publicado', 'orden',
    ];

    protected $casts = [
        'amenidades' => 'array',
        'incluye'    => 'array',
        'disponible' => 'boolean',
        'publicado'  => 'boolean',
        'precio_hora' => 'decimal:2',
    ];

    public function scopeSalonesPublicados($query)
    {
        return $query->where('tipo', 'salon_eventos')
                     ->where('publicado', true)
                     ->orderBy('orden')
                     ->orderBy('nombre');
    }

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
            'salon_eventos' => 'Salón para eventos',
            default => $this->tipo,
        };
    }
}
