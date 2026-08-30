<?php

namespace App\Models;

use App\Enums\CategoriaTema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Fase 4.D — un tema de la oferta de asesoría IYEM.
 *
 * `categoria` no se castea al enum a propósito (mismo criterio que en Espacio y
 * User): un valor que quedara fuera del enum tumbaría la lista entera con un
 * 500. Se resuelve con `categoriaEnum()`, que devuelve null si no reconoce.
 */
class TemaAsesoria extends Model
{
    protected $table = 'temas_asesoria';

    protected $fillable = [
        'nombre', 'descripcion_corta', 'categoria',
        'duracion_min', 'activo', 'validado_iyem', 'orden',
    ];

    protected $casts = [
        'activo'        => 'boolean',
        'validado_iyem' => 'boolean',
        'duracion_min'  => 'integer',
    ];

    public function categoriaEnum(): ?CategoriaTema
    {
        return CategoriaTema::tryFrom((string) $this->categoria);
    }

    public function asesores(): BelongsToMany
    {
        return $this->belongsToMany(Asesor::class, 'asesor_tema');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->orderBy('orden')->orderBy('nombre');
    }
}
