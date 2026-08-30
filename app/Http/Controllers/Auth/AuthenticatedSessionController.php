<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\ExigeSegundoFactor;
use App\Http\Controllers\Auth\Concerns\RedirigeAlPortal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\DosFactores;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    use ExigeSegundoFactor, RedirigeAlPortal;

    public function __construct(private readonly DosFactores $dosFactores)
    {
    }

    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status'           => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $usuario = $request->user();

        // D — Con segundo factor activo, la contraseña **no basta**: se corta
        // aquí, se cierra la sesión que acaba de abrir el guard y solo queda un
        // identificador guardado. Si alguien abandona en el desafío, no ha
        // entrado a ninguna parte.
        //
        // Un dispositivo marcado como de confianza se salta el desafío, que es
        // para lo que existe.
        if ($this->necesitaSegundoFactor($usuario, $request)) {
            return $this->mandarAlDesafio($usuario, $request, $request->boolean('remember'));
        }

        // `regenerate()` a secas deja **viva** la fila de la sesion anterior:
        // con el driver `database` el identificador viejo sigue sirviendo, que
        // es justo lo que regenerar pretende impedir. Ademas aparecia como un
        // dispositivo fantasma en «Mi seguridad». `true` la destruye.
        $request->session()->regenerate(true);

        // Un rol desconocido se detiene aqui con un 403 explicado. Antes se
        // resolvia con `if ($user->esAdmin()) ... else portal`, que mandaba al
        // portal a cualquiera que no fuera admin y arrancaba el rebote de A.1.
        return $this->alDestinoPrevisto($usuario, $request);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
