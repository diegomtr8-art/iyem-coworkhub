<?php

namespace App\Models;

use App\Enums\EstadoAsesoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Una petición de asesoría IYEM (Fase 1.2).
 *
 * Las horas se descuentan **al confirmar**, nunca al solicitar: ver
 * `EstadoAsesoria`. Toda transición de estado pasa por `GestorDeAsesorias`,
 * que es quien mueve la bolsa; cambiar `estado` a mano deja el libro mintiendo.
 */
class SolicitudAsesoria extends Model
{
    use HasFactory;

    protected $table = 'solicitudes_asesoria';

    protected $fillable = [
        'user_id', 'suscripcion_id', 'tema', 'dia_preferido', 'horario_preferido',
        'horas', 'estado', 'asesor_nombre', 'asesor_user_id', 'fecha_confirmada',
        'notas_operativo', 'atendida_por_user_id', 'atendida_en',
    ];

    protected $casts = [
        'dia_preferido'    => 'date',
        'fecha_confirmada' => 'datetime',
        'atendida_en'      => 'datetime',
        'horas'            => 'float',
        'estado'           => EstadoAsesoria::class,
    ];

    public function user()        { return $this->belongsTo(User::class); }
    public function suscripcion() { return $this->belongsTo(Suscripcion::class); }
    public function asesor()      { return $this->belongsTo(User::class, 'asesor_user_id'); }
    public function atendidaPor() { return $this->belongsTo(User::class, 'atendida_por_user_id'); }
    public function movimientos() { return $this->hasMany(MovimientoHoras::class, 'solicitud_asesoria_id'); }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', EstadoAsesoria::Solicitada->value);
    }

    public function scopeDelMiembro(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** Quién la dará: el usuario del sistema si lo hay, si no el nombre escrito a mano. */
    public function asesorLegible(): ?string
    {
        return $this->asesor?->name ?? $this->asesor_nombre;
    }
}
