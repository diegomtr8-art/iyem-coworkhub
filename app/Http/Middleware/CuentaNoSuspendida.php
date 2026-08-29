<?php

namespace App\Http\Middleware;

use App\Enums\EstadoCuenta;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A.5 / F — Una cuenta suspendida no entra a ningún portal.
 *
 * **No puede haber bucle**: la ruta `cuenta.suspendida` no lleva este
 * middleware, así que el destino de la redirección nunca vuelve a redirigir.
 * Es la misma disciplina de A.1: si una pantalla existe para explicar por qué
 * te frenaron, tiene que ser un punto final.
 *
 * Es distinto del rol: el rol dice qué parte del sistema te toca, el estado
 * cuánto puedes hacer dentro de ella.
 */
class CuentaNoSuspendida
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && $usuario->estado === EstadoCuenta::Suspendida) {
            return redirect()->route('cuenta.suspendida');
        }

        return $next($request);
    }
}
