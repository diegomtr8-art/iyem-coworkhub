<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    protected $table = 'reservas';

    protected $fillable = [
        'user_id', 'espacio_id', 'suscripcion_id', 'fecha', 'hora_inicio', 'hora_fin', 'estatus', 'precio_total',
    ];

    protected $casts = [
        'fecha' => 'date',
        'precio_total' => 'decimal:2',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function espacio() { return $this->belongsTo(Espacio::class); }
    public function suscripcion() { return $this->belongsTo(Suscripcion::class); }
    public function checkin() { return $this->hasOne(Checkin::class); }
}
