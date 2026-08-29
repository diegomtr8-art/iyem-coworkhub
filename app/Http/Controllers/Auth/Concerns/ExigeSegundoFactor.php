<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Http\Controllers\Auth\DesafioDosFactoresController;
use App\Models\User;
use App\Support\DosFactores;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * D — El segundo factor se exige en **todos** los caminos de acceso.
 *
 * Esto vive en un trait y no dentro del controlador de la contrasena porque un
 * segundo factor que se puede rodear entrando con Google, o con un enlace
 * magico, no es un segundo factor: es un adorno en uno de los tres caminos.
 * Cualquier via de acceso que se anada manana tiene que pasar por aqui.
 */
trait ExigeSegundoFactor
{
    protected function necesitaSegundoFactor(User $usuario, Request $peticion): bool
    {
        if (! $usuario->tieneDosFactores()) {
            return false;
        }

        // Un dispositivo marcado como de confianza se lo salta: para eso existe.
        return ! app(DosFactores::class)->esDispositivoDeConfianza($usuario, $peticion);
    }

    /**
     * Corta el acceso y manda al desafio.
     *
     * Se cierra la sesion que el guard acaba de abrir y solo queda un
     * identificador guardado: entre la contrasena y el codigo **no hay sesion
     * iniciada**, asi que abandonar en el desafio no deja a nadie dentro.
     */
    protected function mandarAlDesafio(User $usuario, Request $peticion, bool $recordar = true): RedirectResponse
    {
        Auth::guard('web')->logout();

        $peticion->session()->put(DesafioDosFactoresController::CLAVE_PENDIENTE, $usuario->id);
        $peticion->session()->put(DesafioDosFactoresController::CLAVE_RECORDAR, $recordar);

        return redirect()->route('dos-factores.desafio');
    }
}
