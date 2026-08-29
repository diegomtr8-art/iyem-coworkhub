<?php

namespace App\Models;

use App\Enums\AccionOperativa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Una línea de la bitácora de operación (Fase 3.11).
 *
 * Se escribe siempre por `registrar()`, por lo mismo que
 * `EventoAutenticacion`: es lo que garantiza que ninguna fila salga sin IP ni
 * agente, que son justo los dos campos que se olvidan y sin los cuales la
 * bitácora no sirve el día que hace falta.
 */
class EntradaBitacora extends Model
{
    protected $table = 'bitacora_operacion';

    /** Solo `created_at`: una entrada de bitácora no se actualiza jamás. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id', 'sujeto_user_id', 'accion', 'descripcion', 'motivo', 'contexto', 'ip', 'agente',
    ];

    protected function casts(): array
    {
        return [
            'contexto'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor()  { return $this->belongsTo(User::class, 'actor_user_id'); }
    public function sujeto() { return $this->belongsTo(User::class, 'sujeto_user_id'); }

    public function getAccionEnumAttribute(): ?AccionOperativa
    {
        return AccionOperativa::tryFrom((string) $this->accion);
    }

    public function getEtiquetaAttribute(): string
    {
        return $this->accion_enum?->etiqueta() ?? $this->accion;
    }

    /**
     * @param  array<string, mixed>  $contexto
     *
     * @throws InvalidArgumentException si la acción exige motivo y no se da.
     *         Es a propósito una excepción y no un aviso: sin el porqué, la
     *         entrada no sirve, y es mejor que reviente en desarrollo a que
     *         llegue a producción una bitácora llena de filas mudas.
     */
    public static function registrar(
        AccionOperativa $accion,
        string $descripcion,
        ?User $actor = null,
        ?User $sujeto = null,
        ?string $motivo = null,
        array $contexto = [],
    ): self {
        if ($accion->exigeMotivo() && trim((string) $motivo) === '') {
            throw new InvalidArgumentException(
                "La acción «{$accion->etiqueta()}» exige un motivo escrito."
            );
        }

        $peticion = request();

        return static::create([
            'actor_user_id'  => $actor?->id,
            'sujeto_user_id' => $sujeto?->id,
            'accion'         => $accion->value,
            'descripcion'    => mb_substr($descripcion, 0, 500),
            'motivo'         => $motivo,
            'contexto'       => $contexto ?: null,
            'ip'             => $peticion instanceof Request ? $peticion->ip() : null,
            'agente'         => $peticion instanceof Request
                ? mb_substr((string) $peticion->userAgent(), 0, 500)
                : null,
        ]);
    }

    public function scopeRecientes(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeSobre(Builder $query, User $miembro): Builder
    {
        return $query->where('sujeto_user_id', $miembro->id);
    }

    public function scopeDeAccion(Builder $query, AccionOperativa $accion): Builder
    {
        return $query->where('accion', $accion->value);
    }
}
