<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Checkin extends Model
{
    use HasFactory;

    protected $table = 'checkins';

    protected $fillable = [
        'user_id', 'espacio_id', 'reserva_id', 'hora_entrada', 'hora_salida', 'duracion_minutos',
    ];

    protected $casts = [
        'hora_entrada' => 'datetime',
        'hora_salida' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function espacio() { return $this->belongsTo(Espacio::class); }
    public function reserva() { return $this->belongsTo(Reserva::class); }

    public function estaActivo(): bool { return is_null($this->hora_salida); }
}
