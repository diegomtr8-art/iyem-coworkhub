<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Models\Comunicado;
use App\Models\Consentimiento;
use App\Models\User;
use App\Notifications\CuentaYaExiste;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * B — El registro no delata qué correos ya tienen cuenta.
     *
     * La regla `unique` de antes contestaba «ese correo ya está registrado», lo
     * que convertía el formulario de alta en un buscador de miembros de Nódico:
     * bastaba con teclear direcciones y leer el error. Ahora los dos caminos
     * —alta nueva y correo repetido— acaban en la **misma URL**, con la
     * **misma pantalla** y pagando el **mismo bcrypt**, y la aclaración viaja
     * por el único canal al que solo llega el dueño de la dirección.
     *
     * Por eso tampoco se inicia sesión automáticamente: en el caso del correo
     * repetido sería imposible, y las dos respuestas dejarían de parecerse.
     * Además, abrir sesión sobre una dirección que nadie ha demostrado suya es
     * el problema de A.2 en pequeño.
     */
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'name'      => 'required|string|max:255',
            // Sin `unique`: la unicidad se resuelve abajo, en silencio.
            'email'     => 'required|string|lowercase|email|max:255',
            'password'  => ['required', 'confirmed', Rules\Password::defaults()],
            'telefono'  => 'nullable|string|max:20',
            'empresa'   => 'nullable|string|max:255',
            'ocupacion' => 'nullable|string|max:255',

            /*
             * E — La casilla legal se valida **en el servidor**.
             *
             * En el formulario ya bloquea el envio, pero eso es cortesia: sin
             * esta regla bastaria un `curl` para crear cuentas sin
             * consentimiento, y la constancia que guardamos seria falsa.
             */
            'acepta_legales' => 'accepted',
        ], [
            'acepta_legales.accepted' => 'Para crear tu cuenta necesitas aceptar el aviso de privacidad y los terminos.',
        ]);

        $correo = Str::lower(trim($datos['email']));

        if ($existente = User::where('email', $correo)->first()) {
            $this->fingirAlta($datos['password']);

            $enviado = $this->avisarQueYaExiste($existente);
        } else {
            $enviado = $this->crearMiembro($datos, $correo, $request);
        }

        return redirect()
            ->route('registro.revisa-tu-correo')
            ->with('correo', $correo)
            // Los dos caminos informan igual de que el correo no salió: si solo
            // avisara uno, el aviso delataría cuál de los dos fue.
            ->with('correo_enviado', $enviado);
    }

    /** Pantalla común a los dos caminos. */
    public function revisaTuCorreo(Request $request): Response|RedirectResponse
    {
        $correo = $request->session()->get('correo');

        // Entrar aquí a pelo, sin venir del formulario, no dice nada de nadie.
        if (! $correo) {
            return redirect()->route('register');
        }

        return Inertia::render('Auth/RevisaTuCorreo', [
            'correo'        => $correo,
            'correoEnviado' => (bool) $request->session()->get('correo_enviado', true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     *
     * @return bool si el correo de verificación llegó a salir
     */
    private function crearMiembro(array $datos, string $correo, Request $request): bool
    {
        $usuario = new User();

        $usuario->fill([
            'name'      => $datos['name'],
            'email'     => $correo,
            'password'  => $datos['password'],
            'telefono'  => $datos['telefono'] ?? null,
            'empresa'   => $datos['empresa'] ?? null,
            'ocupacion' => $datos['ocupacion'] ?? null,
        ]);

        // A.4 — rol y estado por `forceFill`, fuera del `fill()` de arriba:
        // aunque mañana alguien meta `$request->all()` ahí, el formulario no
        // puede elegir el rol.
        // A.5 — la membresía nace pendiente: existe la cuenta, no el acceso.
        $usuario->forceFill([
            'tipo'          => RolUsuario::Miembro->value,
            'estado_cuenta' => EstadoCuenta::Pendiente->value,
            'face_id_ok'    => false,
        ])->save();

        // E — La constancia se guarda con la version aceptada, la fecha y la IP,
        // que es lo que sirve el dia que alguien lo reclame.
        Consentimiento::registrar($usuario, $request);

        Comunicado::create([
            'user_id' => $usuario->id,
            'titulo'  => '¡Bienvenido a Nódico, ' . explode(' ', $usuario->name)[0] . '!',
            'mensaje' => 'Tu cuenta ya existe. Confirma tu correo para entrar al portal y, cuando contrates un plan o pases por recepción, activamos tu membresía y el registro de Face ID.',
            'tipo'    => 'bienvenida',
            'leido'   => false,
        ]);

        // Dispara el envío del correo de verificación. Un fallo del SMTP ya no
        // tumba la petición: `User::sendEmailVerificationNotification()` lo
        // atrapa y lo deja anotado.
        event(new Registered($usuario));

        return $usuario->correoDeVerificacionEnviado;
    }

    /** @return bool si el aviso llegó a salir */
    private function avisarQueYaExiste(User $existente): bool
    {
        try {
            $existente->notify(new CuentaYaExiste());

            return true;
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el aviso de cuenta existente.', [
                'usuario' => $existente->id,
                'motivo'  => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Paga el mismo coste que costaría crear la cuenta.
     *
     * Guardar un usuario nuevo implica un bcrypt completo. Sin esto, el camino
     * del correo repetido respondería sensiblemente más rápido y un cronómetro
     * volvería a distinguirlos: la fuga que acabamos de tapar, por la puerta
     * de atrás.
     */
    private function fingirAlta(string $contrasena): void
    {
        Hash::make($contrasena);
    }
}
