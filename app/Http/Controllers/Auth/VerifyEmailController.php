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
            $request->session()->regenerate();
        }

        return $this->alPortal($usuario)->with('status', 'correo-verificado');
    }
}
