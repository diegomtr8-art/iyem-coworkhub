<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La suscripción de un miembro en la pasarela (hoy, Openpay): lo que se cobra
 * solo cada periodo. La membresía de Nódico sigue viviendo en `suscripciones`;
 * esto solo sabe si Openpay la va a cobrar y cómo va.
 */
class SuscripcionPasarela extends Model
{
    /** Estados de Openpay en los que la suscripción sigue viva (cobra o reintenta). */
    public const VIVAS = ['trial', 'active', 'past_due'];

    protected $table = 'suscripciones_pasarela';

    protected $fillable = [
        'user_id', 'plan_id', 'pasarela', 'suscripcion_id', 'cliente_id', 'tarjeta_id',
        'plan_pasarela_id', 'cargo_id', 'estado', 'cancelar_al_final',
        'periodo_fin', 'proximo_cobro', 'periodo_actual', 'en_impago', 'sincronizada_en',
    ];

    protected $casts = [
        'cancelar_al_final' => 'boolean',
        'en_impago'         => 'boolean',
        'periodo_fin'       => 'date',
        'proximo_cobro'     => 'date',
        'periodo_actual'    => 'integer',
        'sincronizada_en'   => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plane::class, 'plan_id');
    }

    public function scopeVivas(Builder $q): Builder
    {
        return $q->whereIn('estado', self::VIVAS);
    }

    public function viva(): bool
    {
        return in_array($this->estado, self::VIVAS, true);
    }
}
