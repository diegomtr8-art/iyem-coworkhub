<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnuncioCoworking extends Model
{
    protected $table = 'anuncios_coworking';

    protected $fillable = [
        'titulo', 'contenido', 'tipo', 'fecha_inicio', 'fecha_fin', 'activo',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'activo' => 'boolean',
    ];
}
