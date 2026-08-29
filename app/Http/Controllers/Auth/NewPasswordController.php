<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SesionesActivas;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function __construct(private readonly SesionesActivas $sesiones)
    {
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $estado = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $usuario) use ($request) {
                $usuario->forceFill([
                    'password'       => $request->password,
                    'remember_token' => Str::random(60),
                ])->save();

                // B — Restablecer la contraseña echa a todo el mundo de la
                // cuenta. Quien la restablece suele hacerlo justo porque cree
                // que alguien más entró: dejar vivas las sesiones abiertas
                // vaciaría de sentido el gesto.
                $this->sesiones->cerrarOtras($usuario, null);

                event(new PasswordReset($usuario));
            }
        );

        if ($estado === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', trans($estado));
        }

        // Un token inválido o caducado no delata nada: dice que el enlace ya no
        // sirve, no si el correo tiene cuenta.
        throw ValidationException::withMessages([
            'email' => [trans($estado)],
        ]);
    }
}
