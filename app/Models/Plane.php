<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plane extends Model
{
    protected $table = 'planes';

    protected $fillable = [
        'nombre', 'subtitulo', 'tipo', 'precio',
        'horas_incluidas', 'dias_cowork_mes',
        'horas_sala_mes', 'horas_contenido_mes',
        'max_horas_sala_dia', 'personas',
        'max_reservas_mes', 'acceso_24h', 'incluye_sala_juntas',
        'color', 'destacado', 'activo',
    ];

    protected $casts = [
        'precio'              => 'float',
        'acceso_24h'          => 'boolean',
        'incluye_sala_juntas' => 'boolean',
        'destacado'           => 'boolean',
        'activo'              => 'boolean',
    ];

    public function suscripciones() { return $this->hasMany(Suscripcion::class, 'plan_id'); }

    public function esIlimitado(): bool        { return is_null($this->dias_cowork_mes); }
    public function tieneAccesoSala(): bool    { return !is_null($this->horas_sala_mes); }
    public function tieneAccesoContenido(): bool { return !is_null($this->horas_contenido_mes); }
}
