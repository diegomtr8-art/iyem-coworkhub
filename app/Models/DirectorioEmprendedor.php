<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectorioEmprendedor extends Model
{
    protected $table = 'directorio_emprendedores';

    protected $fillable = [
        'nombre', 'instagram', 'foto', 'descripcion',
        'giro', 'municipio', 'url_destino', 'sitio_web', 'egresado_iyem',
        'destacado_semana', 'elegible_destacado', 'destacado_ultima_vez', 'fijado_hasta',
        'activo', 'orden',
    ];

    protected $casts = [
        'destacado_semana'     => 'boolean',
        'elegible_destacado'   => 'boolean',
        'egresado_iyem'        => 'boolean',
        'activo'               => 'boolean',
        'destacado_ultima_vez' => 'date',
        'fijado_hasta'         => 'date',
    ];

    /** Candidatos a destacado: activos y marcados como elegibles. */
    public function scopeElegibles($query)
    {
        return $query->where('activo', true)->where('elegible_destacado', true);
    }

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
