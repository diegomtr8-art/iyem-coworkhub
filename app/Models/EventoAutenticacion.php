<?php

namespace App\Models;

use App\Enums\EventoAuth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * B — Una línea de la bitácora de autenticación.
 *
 * Se escribe siempre por `registrar()`, que es lo que garantiza que ninguna
 * fila salga sin IP ni agente: son justo los dos campos que se olvidan y sin
 * los cuales la bitácora no sirve para nada el día que hace falta.
 */
class EventoAutenticacion extends Model
{
    protected $table = 'eventos_auth';

    /** Solo `created_at`; una entrada de bitácora no se actualiza jamás. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'correo', 'tipo', 'exito', 'ip', 'agente', 'contexto',
    ];

    protected function casts(): array
    {
        return [
            'exito'      => 'boolean',
            'contexto'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getEventoAttribute(): ?EventoAuth
    {
        return EventoAuth::tryFrom((string) $this->tipo);
    }

    public function getEtiquetaAttribute(): string
    {
        return $this->evento?->etiqueta() ?? $this->tipo;
    }

    /**
     * @param  array<string, mixed>  $contexto
     */
    public static function registrar(
        EventoAuth $tipo,
        ?User $usuario = null,
        ?string $correo = null,
        bool $exito = true,
        array $contexto = [],
    ): self {
        $peticion = request();

        return static::create([
            'user_id'  => $usuario?->id,
            'correo'   => $correo ?? $usuario?->email,
            'tipo'     => $tipo->value,
            'exito'    => $exito,
            'ip'       => $peticion instanceof Request ? $peticion->ip() : null,
            // El agente lo elige el cliente y puede venir con cualquier cosa
            // dentro; se recorta para que una cabecera larguísima no engorde
            // la tabla.
            'agente'   => $peticion instanceof Request
                ? mb_substr((string) $peticion->userAgent(), 0, 500)
                : null,
            'contexto' => $contexto ?: null,
        ]);
    }

    public function scopeRecientes(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeDe(Builder $query, User $usuario): Builder
    {
        return $query->where('user_id', $usuario->id);
    }
}
