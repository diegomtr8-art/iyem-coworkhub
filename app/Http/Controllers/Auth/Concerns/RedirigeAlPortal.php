<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RutaDefinida;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A.1 — Un solo sitio decide a donde va cada quien tras iniciar sesion,
 * verificar el correo o confirmar la contrasena.
 *
 * Antes cada controlador de Breeze redirigia a `dashboard` a secas, asi que un
 * miembro que verificaba su correo aterrizaba en el panel operativo y de ahi
 * salia rebotado. Y si el rol no se reconoce **no se redirige a ninguna parte**:
 * se aborta con 403, que es lo unico que corta el bucle de raiz.
 */
trait RedirigeAlPortal
{
    protected function alPortal(User $usuario): RedirectResponse
    {
        return redirect()->route($this->rutaDelPortal($usuario));
    }

    protected function rutaDelPortal(User $usuario): string
    {
        $ruta = $usuario->rutaInicio();

        if ($ruta === null) {
            abort(403, 'Tu cuenta no tiene un perfil de acceso asignado, así que no sabemos a qué parte de Nódico llevarte. Escríbenos y lo resolvemos.');
        }

        return $ruta;
    }

    /**
     * G — Respeta el destino previsto **solo si pertenece a su portal**.
     *
     * `redirect()->intended()` a secas manda a la ultima URL que la persona
     * intento abrir antes de que le pidieran identificarse. Si un miembro tenia
     * guardada una URL del panel operativo —porque siguio un enlace, o porque
     * la sesion le caduco ahi— entrar **correctamente** lo llevaba a un 403: se
     * identifica bien y aun asi acaba en una pantalla de error.
     *
     * La pertenencia no se adivina por el prefijo de la URL: se resuelve la
     * ruta real y se mira el `portal:` de sus middlewares. Asi, mover o
     * renombrar una ruta no rompe esta comprobacion.
     *
     * De paso cierra un redirector abierto: un destino previsto que apunte a
     * otro host se descarta.
     */
    protected function alDestinoPrevisto(User $usuario, Request $peticion): RedirectResponse
    {
        $porDefecto = route($this->rutaDelPortal($usuario));

        // `pull` y no `get`: si no sirve hay que tirarlo, no dejarlo esperando
        // al siguiente inicio de sesion.
        $previsto = $peticion->session()->pull('url.intended');

        if (is_string($previsto) && $previsto !== '' && $this->esDestinoValido($previsto, $usuario, $peticion)) {
            return redirect()->to($previsto);
        }

        return redirect()->to($porDefecto);
    }

    private function esDestinoValido(string $destino, User $usuario, Request $peticion): bool
    {
        $host = parse_url($destino, PHP_URL_HOST);

        // Nunca fuera de este sitio.
        if ($host !== null && $host !== $peticion->getHost()) {
            return false;
        }

        $ruta = $this->rutaQueAtiende(parse_url($destino, PHP_URL_PATH) ?: '/');

        if ($ruta === null) {
            return false;
        }

        foreach ($ruta->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'portal:')) {
                return substr($middleware, strlen('portal:')) === $usuario->rol?->portal();
            }
        }

        // Rutas sin portal —el perfil, «Mi seguridad»— valen para cualquiera.
        return true;
    }

    private function rutaQueAtiende(string $camino): ?RutaDefinida
    {
        try {
            return Route::getRoutes()->match(Request::create($camino, 'GET'));
        } catch (HttpException) {
            // No hay ruta GET para ese camino: no es un destino al que llevar a nadie.
            return null;
        }
    }
}
