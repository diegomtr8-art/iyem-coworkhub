<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Fase 4.A — registro de webhooks de Stripe ya procesados, para descartar los
 * reintentos. El índice único sobre `stripe_event_id` es la garantía real de
 * idempotencia; este modelo solo lo envuelve.
 */
class EventoStripe extends Model
{
    protected $table = 'eventos_stripe';

    protected $fillable = ['stripe_event_id', 'tipo', 'procesado_en'];

    protected $casts = ['procesado_en' => 'datetime'];

    /**
     * Ejecuta `$fn` **una sola vez** para este evento. Si el evento ya se
     * procesó (o llega en paralelo), no hace nada y devuelve false.
     *
     * El reclamo del evento se hace con un INSERT que puede chocar contra el
     * índice único: quien gana la carrera ejecuta, los demás se descartan. Es
     * lo que cierra el hueco entre «ya existe» y dos procesos a la vez.
     */
    public static function procesarUnaVez(string $eventId, string $tipo, callable $fn): bool
    {
        try {
            static::create([
                'stripe_event_id' => $eventId,
                'tipo'            => $tipo,
                'procesado_en'    => now(),
            ]);
        } catch (QueryException $e) {
            if (static::esClaveRepetida($e)) {
                return false; // ya estaba: reintento de Stripe, se descarta.
            }
            throw $e;
        }

        $fn();

        return true;
    }

    private static function esClaveRepetida(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062      // MySQL
            || str_contains($e->getMessage(), 'UNIQUE');    // SQLite
    }
}
