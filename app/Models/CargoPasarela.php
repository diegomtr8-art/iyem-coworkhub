<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un cargo con tarjeta que Nódico mandó a cobrar a la pasarela (hoy, BBVA).
 *
 * Se crea **antes** de mandar a la persona al banco. Es lo que permite
 * confirmar consultando la API: solo se aceptan ids que estén aquí, de esa
 * persona y por este importe (ver la migración y `ConfirmadorDeCargo`).
 */
class CargoPasarela extends Model
{
    public const CREANDO     = 'creando';
    public const PENDIENTE   = 'pendiente';
    public const COMPLETADO  = 'completado';
    public const FALLIDO     = 'fallido';
    public const CANCELADO   = 'cancelado';
    public const ABANDONADO  = 'abandonado';
    public const DEVUELTO    = 'devuelto';
    public const EN_REVISION = 'en_revision';

    /** Estados en los que ya no hay nada que consultar. */
    public const FINALES = [self::COMPLETADO, self::FALLIDO, self::CANCELADO, self::DEVUELTO, self::EN_REVISION];

    protected $table = 'cargos_pasarela';

    protected $fillable = [
        'user_id', 'plan_id', 'pasarela', 'order_id', 'transaccion_id',
        'importe', 'moneda', 'estado', 'estado_pasarela', 'operacion',
        'origen', 'url_pago', 'url_vuelta', 'ip_cliente',
        'tarjeta_marca', 'tarjeta_ultimos4', 'error_codigo', 'error_mensaje',
        'ultima_consulta_en', 'confirmado_en',
    ];

    protected $casts = [
        'importe'            => 'float',
        'ultima_consulta_en' => 'datetime',
        'confirmado_en'      => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plane::class, 'plan_id');
    }

    public function esFinal(): bool
    {
        return in_array($this->estado, self::FINALES, true);
    }

    public function scopeEsperandoAlBanco(Builder $q): Builder
    {
        return $q->where('estado', self::PENDIENTE)->whereNotNull('transaccion_id');
    }
}
