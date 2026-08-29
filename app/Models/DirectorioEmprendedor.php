<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectorioEmprendedor extends Model
{
    protected $table = 'directorio_emprendedores';

    protected $fillable = [
        'nombre', 'instagram', 'foto', 'descripcion',
        'url_destino', 'destacado_semana', 'activo', 'orden',
    ];

    protected $casts = [
        'destacado_semana' => 'boolean',
        'activo'           => 'boolean',
    ];

    public function scopePublicos($query)
    {
        return $query->where('activo', true)->orderBy('orden')->orderBy('nombre');
    }

    /** El emprendedor destacado de la semana, si hay alguno marcado. */
    public function scopeDeLaSemana($query)
    {
        return $query->where('activo', true)->where('destacado_semana', true);
    }

    /** Enlace de la ficha: el propio si existe, si no su Instagram. */
    public function getEnlaceAttribute(): ?string
    {
        if ($this->url_destino) {
            return $this->url_destino;
        }

        return $this->instagram ? "https://www.instagram.com/{$this->instagram}" : null;
    }
}
