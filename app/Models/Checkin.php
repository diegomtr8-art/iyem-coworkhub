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

    /**
     * La duración como la lee una persona. Diez segundos son cero minutos y el
     * cálculo es correcto, pero «0 minutos» parece un error (pruebas de
     * servicio social, 29-sep-2026): se dice «menos de un minuto».
     */
    public static function duracionEnPalabras(int $minutos): string
    {
        if ($minutos < 1) {
            return 'menos de un minuto';
        }

        if ($minutos < 60) {
            return $minutos === 1 ? '1 minuto' : "{$minutos} minutos";
        }

        $horas = intdiv($minutos, 60);
        $resto = $minutos % 60;

        return $resto ? "{$horas} h {$resto} min" : "{$horas} h";
    }
}
