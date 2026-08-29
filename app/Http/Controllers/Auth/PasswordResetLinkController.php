<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * B — La recuperación responde igual exista o no la cuenta.
     *
     * Antes, un correo sin cuenta lanzaba un error de validación con el texto
     * de `passwords.user` y uno con cuenta devolvía el de `passwords.sent`:
     * dos respuestas distintas, o sea un buscador de miembros de Nódico.
     *
     * Ahora el resultado del broker se descarta a propósito. `Password::
     * sendResetLink` ya trae su propio límite por correo (`auth.passwords.
     * users.throttle`), así que tampoco se puede usar para sondear a base de
     * repetir.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', trans('passwords.sent'));
    }
}
