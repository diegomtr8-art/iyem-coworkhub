<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * E — Nadie usa el portal sin haber aceptado la version vigente.
 *
 * **Sin bucle**, con la misma disciplina de A.1: la ruta del consentimiento no
 * lleva este middleware, asi que el destino de la redireccion nunca vuelve a
 * redirigir. Tampoco se aplica a cerrar sesion: quien no quiera aceptar tiene
 * que poder salir.
 */
class ConsentimientoVigente
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && $usuario->consentimientosPendientes() !== []) {
            return redirect()->route('consentimiento');
        }

        return $next($request);
    }
}
