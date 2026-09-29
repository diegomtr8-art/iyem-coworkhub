<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Eventos de pago ya procesados, para descartar los repetidos. El índice único
 * sobre (pasarela, evento_id) es la garantía real de idempotencia; este modelo
 * solo lo envuelve.
 *
 * - Stripe: el id del evento del webhook (Stripe reintenta).
 * - BBVA: `{transacción}:{estado}` de la consulta del cargo (la persona recarga
 *   la página de regreso, y el proceso programado consulta el mismo cargo).
 *
 * Antes era `EventoStripe` (tabla `eventos_stripe`).
 */
class EventoPasarela extends Model
{
    protected $table = 'eventos_pasarela';

    protected $fillable = ['pasarela', 'evento_id', 'tipo', 'procesado_en'];

    protected $casts = ['procesado_en' => 'datetime'];

    /**
     * Ejecuta `$fn` **una sola vez** para este evento. Si el evento ya se
     * procesó (o llega en paralelo), no hace nada y devuelve false.
     *
     * El reclamo del evento se hace con un INSERT que puede chocar contra el
     * índice único: quien gana la carrera ejecuta, los demás se descartan. Es
     * lo que cierra el hueco entre «ya existe» y dos procesos a la vez.
     */
    public static function procesarUnaVez(string $pasarela, string $eventoId, string $tipo, callable $fn): bool
    {
        try {
            static::create([
                'pasarela'     => $pasarela,
                'evento_id'    => $eventoId,
                'tipo'         => $tipo,
                'procesado_en' => now(),
            ]);
        } catch (QueryException $e) {
            if (static::esClaveRepetida($e)) {
                return false; // ya estaba: reintento, se descarta.
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
