<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ajuste extends Model
{
    protected $table = 'ajustes';

    protected $fillable = ['clave', 'valor', 'descripcion'];

    protected $casts = ['valor' => 'array'];

    /**
     * Lee un ajuste sin reventar si la tabla aún no existe (p. ej. durante
     * migraciones o al desplegar por primera vez).
     *
     * BE-05: antes se tragaba cualquier Throwable en silencio y un fallo real de
     * base de datos dejaba la sección vacía sin que nadie se enterara. Ahora se
     * registra, salvo en consola, donde la tabla puede no existir todavía.
     */
    public static function obtener(string $clave, mixed $porDefecto = null): mixed
    {
        try {
            return static::where('clave', $clave)->value('valor') ?? $porDefecto;
        } catch (\Throwable $e) {
            if (! app()->runningInConsole()) {
                \Illuminate\Support\Facades\Log::warning('No se pudo leer el ajuste', [
                    'clave' => $clave,
                    'error' => $e->getMessage(),
                ]);
            }

            return $porDefecto;
        }
    }
}
