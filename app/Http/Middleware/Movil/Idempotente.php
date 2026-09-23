<?php

namespace App\Http\Middleware\Movil;

use App\Exceptions\ErrorDeApi;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Idempotency-Key` obligatoria en las escrituras que cuestan algo (reservar,
 * pedir asesoría, pagar).
 *
 * En el teléfono la red se corta a media petición y la app reintenta. Sin esto,
 * el reintento de una reserva que sí llegó al servidor es una segunda reserva.
 * Con esto, la segunda petición con la misma clave recibe la misma respuesta
 * que la primera y no toca nada.
 *
 * Se guardan 24 h las respuestas que no son error del servidor (un 500 se puede
 * reintentar de verdad). El candado evita que dos peticiones simultáneas con la
 * misma clave pasen las dos.
 */
class Idempotente
{
    private const HORAS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        $clave = (string) $request->header('Idempotency-Key', '');

        if (! preg_match('/^[A-Za-z0-9_-]{8,100}$/', $clave)) {
            throw new ErrorDeApi(400, 'falta_idempotencia', 'Falta la cabecera Idempotency-Key.');
        }

        $id = 'idem:' . $request->user()->id . ':' . sha1($request->method() . ' ' . $request->path() . ' ' . $clave);

        return Cache::lock($id . ':candado', 30)->block(15, function () use ($id, $request, $next) {
            $guardada = Cache::get($id);

            if (is_array($guardada)) {
                return new JsonResponse($guardada['cuerpo'], $guardada['estado'], ['Idempotent-Replayed' => 'true']);
            }

            $respuesta = $next($request);

            if ($respuesta->getStatusCode() < 500 && $respuesta instanceof JsonResponse) {
                Cache::put($id, [
                    'estado' => $respuesta->getStatusCode(),
                    'cuerpo' => $respuesta->getData(true),
                ], now()->addHours(self::HORAS));
            }

            return $respuesta;
        });
    }
}
