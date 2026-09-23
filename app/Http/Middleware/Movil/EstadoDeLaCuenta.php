<?php

namespace App\Http\Middleware\Movil;

use App\Enums\EstadoCuenta;
use App\Exceptions\ErrorDeApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los tres frenos del portal web —suspendida, correo sin verificar,
 * consentimiento pendiente— en versión JSON.
 *
 * En la web redirigen a una pantalla; aquí responden `403` con un `codigo` para
 * que la app enseñe **su** pantalla explicada, nunca un error genérico. El orden
 * es fijo (§4.3) para que la app vea siempre el primer problema que tiene que
 * resolver.
 *
 * Con el parámetro `sin-consentimiento` se salta el último: las rutas que
 * muestran y aceptan el consentimiento no pueden exigirlo. Con `solo-suspension`
 * solo mira la suspensión (reenviar la verificación no puede exigir el correo
 * verificado).
 */
class EstadoDeLaCuenta
{
    public function handle(Request $request, Closure $next, ?string $opcion = null): Response
    {
        $usuario = $request->user();

        if ($usuario->estado === EstadoCuenta::Suspendida) {
            throw new ErrorDeApi(403, 'cuenta_suspendida', 'Tu cuenta está suspendida.', [
                'detalle'  => $usuario->estado->descripcion(),
                'contacto' => 'contacto@nodico.com.mx',
            ]);
        }

        if ($opcion === 'solo-suspension') {
            return $next($request);
        }

        if (! $usuario->hasVerifiedEmail()) {
            throw new ErrorDeApi(403, 'correo_sin_verificar', 'Confirma tu correo para empezar a usar Nódico.', [
                'correo' => $usuario->email,
            ]);
        }

        if ($opcion !== 'sin-consentimiento') {
            $pendientes = $usuario->consentimientosPendientes();

            if ($pendientes !== []) {
                throw new ErrorDeApi(403, 'consentimiento_pendiente', 'Actualizamos nuestros documentos legales. Revísalos para continuar.', [
                    'documentos' => $pendientes,
                ]);
            }
        }

        return $next($request);
    }
}
