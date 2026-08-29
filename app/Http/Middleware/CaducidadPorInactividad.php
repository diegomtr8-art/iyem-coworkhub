<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * B — Caducidad por inactividad, distinta en cada portal.
 *
 * `SESSION_LIFETIME` es un único número para toda la aplicación, y aquí hacen
 * falta dos: el panel operativo se usa en una recepción, en un equipo
 * compartido y a la vista de quien pase, así que su sesión debe morir antes
 * que la de un miembro que entra desde su propio teléfono.
 *
 * Se aplica sobre una marca de tiempo propia en la sesión y no tocando
 * `config('session.lifetime')` a mitad de petición: cambiar esa configuración
 * después de que el gestor de sesiones ya arrancó no afecta a la cookie que se
 * emitió, y da la falsa impresión de estar funcionando.
 */
class CaducidadPorInactividad
{
    public function handle(Request $request, Closure $next, string $minutos): Response
    {
        $limite = max(1, (int) $minutos) * 60;
        $sesion = $request->session();
        $ultima = $sesion->get('ultima_actividad');

        if ($ultima !== null && (now()->getTimestamp() - (int) $ultima) > $limite) {
            Auth::guard('web')->logout();
            $sesion->invalidate();
            $sesion->regenerateToken();

            return redirect()
                ->route('login')
                ->with('status', 'Cerramos tu sesión por inactividad. Vuelve a entrar.');
        }

        $sesion->put('ultima_actividad', now()->getTimestamp());

        return $next($request);
    }
}
