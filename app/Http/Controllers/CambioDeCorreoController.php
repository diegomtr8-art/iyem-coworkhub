<?php

namespace App\Http\Controllers;

use App\Enums\EventoAuth;
use App\Http\Controllers\Auth\Concerns\RedirigeAlPortal;
use App\Models\EventoAutenticacion;
use App\Models\User;
use App\Notifications\AvisoDeCambioDeCorreo;
use App\Notifications\VerificarCorreoNuevo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * Fase 4.C — cambio de correo con el patrón seguro, servido a los dos portales.
 *
 * El correo es la llave de recuperación de la cuenta, así que cambiarlo se trata
 * como la acción más delicada del perfil:
 *
 *   1. Pedir el cambio va detrás de `password.confirm` (la ruta lo exige): sin
 *      la contraseña actual no se puede ni empezar.
 *   2. El correo **no se toca** hasta que la dirección nueva se confirma con un
 *      enlace firmado que caduca. Verificar demuestra acceso a esa bandeja.
 *   3. A la dirección **anterior** le llega un aviso en cuanto se pide el cambio,
 *      para que el dueño reaccione si no fue él.
 *
 * Es el mismo controlador para miembro y operativo; a dónde vuelve cada quien lo
 * decide `RedirigeAlPortal`.
 */
class CambioDeCorreoController extends Controller
{
    use RedirigeAlPortal;

    /** Paso 1: se registra la solicitud y salen los dos correos. */
    public function solicitar(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                // No puede ser un correo ya usado por otra cuenta —ni por la
                // propia: cambiar el correo por el que ya se tiene no es cambiar.
                Rule::unique('users', 'email'),
            ],
        ], [
            'email.unique' => 'Ese correo ya está en uso.',
        ]);

        $token = $usuario->solicitarCambioDeCorreo($datos['email']);

        $url = URL::temporarySignedRoute(
            'seguridad.correo.verificar',
            now()->addMinutes(User::CAMBIO_CORREO_MINUTOS),
            ['user' => $usuario->id, 'token' => $token],
        );

        // A la dirección nueva: el enlace de confirmación. Va on-demand porque el
        // usuario todavía tiene el correo viejo en su fila.
        Notification::route('mail', $datos['email'])->notify(new VerificarCorreoNuevo($url));

        // A la dirección anterior: el aviso de que alguien pidió el cambio.
        $usuario->notify(new AvisoDeCambioDeCorreo($datos['email']));

        EventoAutenticacion::registrar(
            EventoAuth::CambioCorreoSolicitado,
            $usuario,
            contexto: ['correo_nuevo' => $datos['email']],
        );

        return back()->with('success', 'Te enviamos un enlace a tu dirección nueva para confirmarla. Tu correo actual sigue funcionando hasta entonces.');
    }

    /**
     * Paso 2: el enlace de la dirección nueva. Firmado y de un solo uso.
     *
     * No exige sesión iniciada a propósito: la persona suele abrir el correo en
     * otro dispositivo. La firma más el token en base de datos son la garantía;
     * al aplicarse, el token se borra y el enlace deja de valer.
     */
    public function verificar(Request $request, User $user, string $token): RedirectResponse
    {
        if (! $user->tokenDeCambioValido($token)) {
            // Enlace caducado, ya usado o manipulado. No se distingue cuál, para
            // no dar pistas.
            return redirect()->route('login')
                ->with('error', 'Ese enlace de cambio de correo no es válido o ya caducó. Pídelo de nuevo desde «Mi seguridad».');
        }

        $anterior = $user->aplicarCambioDeCorreo();

        // El dueño de la dirección anterior se entera de que el cambio se
        // consumó, no solo de que se pidió.
        Notification::route('mail', $anterior)->notify(new AvisoDeCambioDeCorreo($user->email));

        EventoAutenticacion::registrar(
            EventoAuth::CambioCorreoAplicado,
            $user,
            contexto: ['correo_anterior' => $anterior],
        );

        // Si quien confirma es la misma sesión, se renueva su identificador:
        // cambió lo que identifica a la cuenta. Misma regla que al verificar el
        // correo inicial o al iniciar sesión.
        if ($request->user()?->is($user)) {
            $request->session()->regenerate(true);

            return $this->alPortal($user)->with('success', 'Tu correo se cambió correctamente.');
        }

        return redirect()->route('login')->with('success', 'Tu correo se cambió correctamente. Ya puedes entrar con él.');
    }

    /** Cancela una solicitud pendiente: «no fui yo» o «me equivoqué». */
    public function cancelar(Request $request): RedirectResponse
    {
        $request->user()->cancelarCambioDeCorreo();

        return back()->with('info', 'Cancelamos la solicitud de cambio de correo.');
    }
}
