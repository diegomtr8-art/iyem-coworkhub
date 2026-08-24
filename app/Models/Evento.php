<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    protected $fillable = [
        'titulo', 'descripcion', 'fecha', 'hora_inicio', 'hora_fin',
        'lugar', 'imagen', 'cupo_maximo', 'precio', 'solo_miembros', 'activo',
    ];

    protected $casts = [
        'fecha'         => 'date',
        'solo_miembros' => 'boolean',
        'activo'        => 'boolean',
        'precio'        => 'float',
    ];

    public function scopeActivos($query)  { return $query->where('activo', true); }
    public function scopeProximos($query) { return $query->where('fecha', '>=', today())->orderBy('fecha'); }
    public function scopePasados($query)  { return $query->where('fecha', '<', today())->orderByDesc('fecha'); }
}
