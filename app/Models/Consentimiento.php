<?php

namespace App\Models;

use App\Support\DocumentosLegales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * E — Una aceptacion concreta de un documento legal.
 *
 * Nunca se actualiza ni se borra al aceptar una version nueva: se anade otra
 * fila. El historial es la constancia.
 */
class Consentimiento extends Model
{
    protected $table = 'consentimientos';

    protected $fillable = ['user_id', 'documento', 'version', 'aceptado_en', 'ip', 'agente'];

    protected function casts(): array
    {
        return ['aceptado_en' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getEtiquetaAttribute(): string
    {
        return match ($this->documento) {
            'privacidad' => 'Aviso de privacidad',
            'terminos'   => 'Terminos y condiciones',
            default      => Str::headline($this->documento),
        };
    }

    /**
     * Deja constancia de que esta persona acepto las versiones vigentes.
     *
     * La IP y el agente se toman de la peticion que trae la aceptacion, que es
     * el unico momento en que significan algo.
     */
    public static function registrar(User $usuario, Request $peticion): void
    {
        $vigentes = app(DocumentosLegales::class)->versiones();

        foreach ($vigentes as $documento => $version) {
            static::create([
                'user_id'     => $usuario->id,
                'documento'   => $documento,
                'version'     => $version,
                'aceptado_en' => now(),
                'ip'          => $peticion->ip(),
                'agente'      => Str::limit((string) $peticion->userAgent(), 500),
            ]);
        }
    }
}
