<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EventoAuth;
use App\Http\Controllers\Controller;
use App\Models\EventoAutenticacion;
use App\Support\SesionesActivas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function __construct(private readonly SesionesActivas $sesiones)
    {
    }

    /**
     * B — Cambiar la contraseña cierra las demás sesiones.
     *
     * Es lo que hace que cambiarla sirva de algo cuando alguien ya está dentro:
     * si las sesiones abiertas sobreviven al cambio, quien entró con la
     * contraseña vieja sigue dentro y la persona cree que ya lo echó.
     */
    public function update(Request $request): RedirectResponse
    {
        $validado = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', Password::defaults(), 'confirmed'],
        ]);

        $usuario = $request->user();

        $usuario->forceFill(['password' => $validado['password']])->save();

        // Renueva el hash que Laravel guarda en la sesión actual y dispara
        // `OtherDeviceLogout`; el borrado de filas es lo que de verdad mata las
        // sesiones guardadas en la tabla.
        auth()->logoutOtherDevices($validado['password']);
        $cerradas = $this->sesiones->cerrarOtras($usuario, $request->session()->getId());

        // Cambio de privilegio: identificador de sesión nuevo.
        $request->session()->regenerate();

        EventoAutenticacion::registrar(
            EventoAuth::CambioContrasena,
            $usuario,
            contexto: ['sesiones_cerradas' => $cerradas],
        );

        return back()->with('success', $cerradas > 0
            ? 'Contraseña actualizada. Cerramos tus sesiones en otros dispositivos.'
            : 'Contraseña actualizada.');
    }
}
