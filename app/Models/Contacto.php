<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contacto extends Model
{
    protected $table = 'contactos';

    protected $fillable = [
        'nombre', 'telefono', 'telefono_e164', 'email', 'empresa', 'asunto', 'comentarios', 'ip', 'atendido',
    ];

    protected $casts = [
        'atendido' => 'boolean',
    ];
}
