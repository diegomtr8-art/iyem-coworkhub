<?php

namespace App\Http\Middleware\Movil;

use App\Exceptions\ErrorDeApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta con `409 version_obsoleta` a una app por debajo de la versión mínima, y
 * con `503 mantenimiento` si la app está en pausa.
 *
 * Las apps viejas siguen instaladas meses en los teléfonos; esta es la palanca
 * para retirarlas cuando un cambio del servidor ya no les sirve. Sin cabecera
 * no se corta: una petición sin versión no es de la app (pruebas, curl) y
 * rechazarla no protege nada.
 */
class VersionDeLaApp
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('nodico.app_movil.mantenimiento')) {
            throw new ErrorDeApi(503, 'mantenimiento', (string) config('nodico.app_movil.mensaje_mantenimiento'));
        }

        $version    = (string) $request->header('X-App-Version', '');
        $plataforma = strtolower((string) $request->header('X-App-Plataforma', ''));
        $minima     = config("nodico.app_movil.version_minima.{$plataforma}");

        if ($version !== '' && is_string($minima) && version_compare($version, $minima, '<')) {
            throw new ErrorDeApi(409, 'version_obsoleta', 'Hay una versión nueva de la app de Nódico. Actualízala para seguir.', [
                'version_minima' => $minima,
            ]);
        }

        return $next($request);
    }
}
