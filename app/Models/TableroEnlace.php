<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Un enlace público del tablero. Solo lectura; se puede revocar.
 */
class TableroEnlace extends Model
{
    protected $table = 'tablero_enlaces';

    protected $fillable = ['token', 'nombre', 'ultimo_uso_en', 'revocado_en', 'creado_por_user_id'];

    protected $casts = [
        'ultimo_uso_en' => 'datetime',
        'revocado_en'   => 'datetime',
    ];

    public static function generar(string $nombre, ?int $creadoPor = null): self
    {
        return static::create([
            'token'              => Str::random(48),
            'nombre'             => $nombre,
            'creado_por_user_id' => $creadoPor,
        ]);
    }

    public function estaVigente(): bool
    {
        return $this->revocado_en === null;
    }
}
