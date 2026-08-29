<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

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
}
