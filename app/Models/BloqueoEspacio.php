<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un espacio no disponible por mantenimiento o evento privado (Fase 1.4).
 *
 * Un bloqueo **compite con las reservas igual que cualquier otra**: ocupa
 * bloques en `bloques_reserva`, la misma tabla, así que el índice único enfrenta
 * bloqueos contra reservas sin una línea de código extra. No hay «prioridad»
 * que programar: quien llega primero, ocupa.
 */
class BloqueoEspacio extends Model
{
    use HasFactory;

    protected $table = 'bloqueos_espacio';

    protected $fillable = [
        'espacio_id', 'fecha', 'hora_inicio', 'hora_fin', 'motivo', 'notas', 'creado_por_user_id',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function espacio()   { return $this->belongsTo(Espacio::class); }
    public function creadoPor() { return $this->belongsTo(User::class, 'creado_por_user_id'); }

    public function duracionEnHoras(): float
    {
        return Reserva::calcularHoras($this->hora_inicio, $this->hora_fin);
    }
}
