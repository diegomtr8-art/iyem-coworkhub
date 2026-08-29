<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Auth\EmailVerificationRequest;

/**
 * A.2 — El enlace de verificacion es de un solo uso.
 *
 * El hash firmado incluye el nonce de `User::getEmailForVerification()`, que se
 * borra al verificar; a partir de ahi el enlace ya no valida contra nada. El
 * unico caso que hay que salvar es el de quien vuelve a pulsar su propio enlace
 * ya gastado: no merece un 403, merece que lo lleven a su portal.
 */
class VerificarCorreoRequest extends EmailVerificationRequest
{
    public function authorize(): bool
    {
        if ($this->user()?->hasVerifiedEmail()) {
            return true;
        }

        return parent::authorize();
    }
}
