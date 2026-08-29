<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A.1 — Sustituye a `EsAdmin` y `EsMiembro`.
 *
 * Los dos middlewares anteriores se mandaban el uno al otro: `EsAdmin`
 * redirigia a `portal.dashboard` a quien no fuera admin, y `EsMiembro`
 * redirigia a `dashboard` a quien no fuera miembro. Un usuario cuyo `tipo` no
 * fuera exactamente uno de esos dos valores —nulo, un rol nuevo, una fila
 * creada a mano— no satisfacia ninguna de las dos condiciones, asi que rebotaba
 * entre /dashboard y /portal hasta que el navegador cortaba la cadena.
 *
 * Aqui **no hay ninguna redireccion**: o pasas, o recibes un 403 explicado con
 * salida. El bucle no esta arreglado, es imposible de construir. Y la
 * comparacion es contra el portal que declara el rol, no contra una lista de
 * valores literales, de modo que anadir un cuarto rol no obliga a revisar
 * ninguna ruta.
 */
class PerteneceAlPortal
{
    public function handle(Request $request, Closure $next, string $portal): Response
    {
        $usuario = $request->user();

        // Sin sesion no es asunto de este middleware: `auth` va antes y manda al login.
        if (! $usuario) {
            abort(403, 'Necesitas iniciar sesión para entrar aquí.');
        }

        $rol = $usuario->rol;

        if ($rol === null) {
            abort(403, 'Tu cuenta no tiene un perfil de acceso asignado, así que no sabemos a qué parte de Nódico llevarte. Escríbenos y lo resolvemos.');
        }

        if ($rol->portal() !== $portal) {
            abort(403, $portal === 'operativo'
                ? 'Esta sección es del equipo de Nódico. Tu cuenta es de miembro, y tu espacio es el portal.'
                : 'Esta sección es el portal de miembros. Tu cuenta pertenece al equipo de Nódico.');
        }

        return $next($request);
    }
}
