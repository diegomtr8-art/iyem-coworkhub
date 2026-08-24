<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'tipo', 'telefono', 'empresa',
        'avatar', 'face_id_ok', 'ocupacion', 'notas_admin',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'face_id_ok'        => 'boolean',
        ];
    }

    public function esAdmin(): bool   { return $this->tipo === 'admin'; }
    public function esMiembro(): bool { return $this->tipo === 'miembro'; }

    public function suscripciones()  { return $this->hasMany(Suscripcion::class); }
    public function suscripcionActiva() { return $this->hasOne(Suscripcion::class)->where('estatus', 'Activa')->latest(); }
    public function reservas()        { return $this->hasMany(Reserva::class); }
    public function checkins()        { return $this->hasMany(Checkin::class); }
    public function facturas()        { return $this->hasMany(Factura::class); }
    public function comunicados()     { return $this->hasMany(Comunicado::class); }
}
