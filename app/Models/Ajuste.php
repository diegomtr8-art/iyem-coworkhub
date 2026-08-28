<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ajuste extends Model
{
    protected $table = 'ajustes';

    protected $fillable = ['clave', 'valor', 'descripcion'];

    protected $casts = ['valor' => 'array'];

    /** Lee un ajuste sin reventar si la tabla aún no existe (p. ej. durante migraciones). */
    public static function obtener(string $clave, mixed $porDefecto = null): mixed
    {
        try {
            return static::where('clave', $clave)->value('valor') ?? $porDefecto;
        } catch (\Throwable) {
            return $porDefecto;
        }
    }
}
