<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EventoAuth;
use App\Http\Controllers\Auth\Concerns\RedirigeAlPortal;
use App\Http\Controllers\Controller;
use App\Models\EventoAutenticacion;
use App\Models\User;
use App\Support\DosFactores;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * D — Desafío del segundo factor: pantalla propia después de la contraseña.
 *
 * **No** es un campo más en el formulario de acceso, y la diferencia no es
 * estética: con un campo suelto habría que abrir la app de códigos *antes* de
 * saber siquiera si la contraseña era correcta, y el código de 30 segundos
 * habría caducado al llegar. Separado, la contraseña se valida primero y el
 * código se pide con el reloj recién empezado.
 *
 * Entre la contraseña y el código **no hay sesión iniciada**: solo queda un
 * identificador en la sesión. Si alguien abandona aquí, no ha entrado a nada.
 */
class DesafioDosFactoresController extends Controller
{
    use RedirigeAlPortal;

    public const CLAVE_PENDIENTE = 'dos_factores.usuario_pendiente';
    public const CLAVE_RECORDAR  = 'dos_factores.recordar';

    public function __construct(private readonly DosFactores $dosFactores)
    {
    }

    public function mostrar(Request $request): Response|RedirectResponse
    {
        if (! $this->usuarioPendiente($request)) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * @throws ValidationException
     */
    public function verificar(Request $request): RedirectResponse
    {
        $usuario = $this->usuarioPendiente($request);

        if (! $usuario) {
            return redirect()->route('login')->with(
                'status',
                'La sesión de acceso caducó. Vuelve a entrar con tu contraseña.',
            );
        }

        $request->validate([
            'codigo'              => 'nullable|string',
            'codigo_recuperacion' => 'nullable|string',
        ]);

        $codigo       = $request->string('codigo')->toString();
        $recuperacion = $request->string('codigo_recuperacion')->toString();

        if ($recuperacion !== '') {
            $valido = $this->dosFactores->consumirCodigoDeRecuperacion($usuario, $recuperacion);
            $campo  = 'codigo_recuperacion';
            $via    = 'codigo_recuperacion';
        } else {
            $valido = $this->dosFactores->verificarCodigo($usuario->dos_factores_secreto, $codigo);
            $campo  = 'codigo';
            $via    = 'totp';
        }

        if (! $valido) {
            EventoAutenticacion::registrar(
                EventoAuth::IngresoFallido,
                $usuario,
                exito: false,
                contexto: ['motivo' => 'segundo_factor_incorrecto', 'via' => $via],
            );

            throw ValidationException::withMessages([
                $campo => $campo === 'codigo'
                    ? 'Ese código no es válido. Prueba con el siguiente que muestre tu app.'
                    : 'Ese código de recuperación no es válido o ya se usó.',
            ]);
        }

        $recordar = (bool) $request->session()->pull(self::CLAVE_RECORDAR, false);

        $request->session()->forget(self::CLAVE_PENDIENTE);

        Auth::login($usuario, $recordar);
        $request->session()->regenerate(true);

        if ($request->boolean('confiar_en_dispositivo')) {
            $this->dosFactores->confiarEnEsteDispositivo($usuario, $request);
        }

        return redirect()->intended(route($this->rutaDelPortal($usuario)));
    }

    /**
     * La cuenta que superó la contraseña y está a medio entrar.
     *
     * Se relee de la base en cada petición en vez de guardar el modelo en la
     * sesión: si entre un paso y otro se suspendió la cuenta o se apagó el
     * segundo factor, tiene que notarse aquí.
     */
    private function usuarioPendiente(Request $request): ?User
    {
        $id = $request->session()->get(self::CLAVE_PENDIENTE);

        if (! $id) {
            return null;
        }

        $usuario = User::find($id);

        return $usuario?->tieneDosFactores() ? $usuario : null;
    }
}
