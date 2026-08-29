<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\RedirigeAlPortal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerificarCorreoRequest;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    use RedirigeAlPortal;

    public function __invoke(VerificarCorreoRequest $request): RedirectResponse
    {
        $usuario = $request->user();

        if (! $usuario->hasVerifiedEmail()) {
            $usuario->marcarCorreoVerificado();

            event(new Verified($usuario));

            // Verificar el correo cambia lo que la sesion puede hacer, asi que
            // se renueva su identificador. Es la misma regla que en el login.
            // `regenerate()` a secas deja **viva** la fila de la sesion anterior:
                // con el driver `database` el identificador viejo sigue sirviendo, que
                // es justo lo que regenerar pretende impedir. Ademas aparecia como un
                // dispositivo fantasma en «Mi seguridad». `true` la destruye.
            $request->session()->regenerate(true);
        }

        return $this->alPortal($usuario)->with('status', 'correo-verificado');
    }
}
