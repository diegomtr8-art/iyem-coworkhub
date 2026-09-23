<?php

namespace App\Http\Middleware\Movil;

use App\Exceptions\ErrorDeApi;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * La app tiene dos caras y cada token abre solo una (docs/API-MOVIL.md §2):
 * `miembro` —el portal— o `reportes` —solo lectura, administración—.
 *
 * La habilidad del token no basta: el rol y el permiso se vuelven a comprobar
 * en cada petición. Si a un admin le quitan `ver-reportes`, o a un miembro lo
 * pasan a staff, su token deja de servir en la siguiente llamada, sin esperar a
 * que caduque.
 */
class CaraDelToken
{
    public function handle(Request $request, Closure $next, string $cara): Response
    {
        /** @var User|null $usuario */
        $usuario = $request->user();

        if (! $usuario || ! $usuario->tokenCan($cara) || ! self::puedeUsar($usuario, $cara)) {
            throw new ErrorDeApi(403, 'sin_permiso', 'Esta sección no es para tu tipo de cuenta.');
        }

        return $next($request);
    }

    /** Qué cara le toca a una cuenta, o `null` si ninguna (staff, caja). */
    public static function paraUsuario(User $usuario): ?string
    {
        foreach (['miembro', 'reportes'] as $cara) {
            if (self::puedeUsar($usuario, $cara)) {
                return $cara;
            }
        }

        return null;
    }

    public static function puedeUsar(User $usuario, string $cara): bool
    {
        return match ($cara) {
            'miembro'  => $usuario->esMiembro(),
            'reportes' => $usuario->esOperativo() && Gate::forUser($usuario)->allows('ver-reportes'),
            default    => false,
        };
    }
}
