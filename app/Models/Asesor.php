<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un asesor del IYEM (Fase 3.7).
 *
 * Decisión de Nódico: es un **catálogo, no usuarios**. No tienen cuenta ni
 * entran al sistema; administración los da de alta y recepción los elige de la
 * lista al confirmar una asesoría.
 *
 * Darlos de baja no borra el histórico: `solicitudes_asesoria.asesor_nombre`
 * guarda el nombre tal como era al confirmar, de modo que una asesoría de hace
 * un año sigue diciendo quién la dio aunque esa persona ya no esté.
 */
class Asesor extends Model
{
    use HasFactory;

    protected $table = 'asesores';

    protected $fillable = [
        'nombre', 'foto', 'especialidad', 'semblanza', 'disponibilidad',
        'email', 'telefono', 'notas', 'activo',
    ];

    protected $casts = ['activo' => 'boolean'];

    public function solicitudes()
    {
        return $this->hasMany(SolicitudAsesoria::class, 'asesor_id');
    }

    /** Fase 4.D — los temas que este asesor imparte (sus especialidades). */
    public function temas(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(TemaAsesoria::class, 'asesor_tema');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->orderBy('nombre');
    }

    /** Horas confirmadas o realizadas en un periodo, para ver la carga. */
    public function horasEntre(string $desde, string $hasta): float
    {
        return round((float) $this->solicitudes()
            ->whereIn('estado', ['confirmada', 'realizada'])
            ->whereBetween('fecha_confirmada', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->sum('horas'), 2);
    }
}
