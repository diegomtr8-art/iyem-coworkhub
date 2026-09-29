<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El plan de la pasarela (hoy, Openpay) que corresponde a un plan de Nódico.
 * Un cambio de precio crea uno nuevo; el anterior queda inactivo.
 */
class PlanPasarela extends Model
{
    protected $table = 'planes_pasarela';

    protected $fillable = ['plan_id', 'pasarela', 'plan_pasarela_id', 'importe', 'activo'];

    protected $casts = ['importe' => 'float', 'activo' => 'boolean'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plane::class, 'plan_id');
    }

    /** El plan vigente de la pasarela para este plan de Nódico, si está sincronizado a su precio actual. */
    public static function vigente(Plane $plan, string $pasarela): ?self
    {
        return static::where('plan_id', $plan->id)
            ->where('pasarela', $pasarela)
            ->where('activo', true)
            ->latest('id')
            ->first();
    }
}
