<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EventoAuth;
use App\Http\Controllers\Controller;
use App\Models\EventoAutenticacion;
use App\Support\DosFactores;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * D — Alta y baja del segundo factor, desde el perfil de cada persona.
 *
 * Todo este controlador va detrás de `password.confirm`: activar o quitar el
 * segundo factor con una sesión olvidada en un equipo prestado sería regalar
 * justo lo que el segundo factor protege.
 */
class DosFactoresController extends Controller
{
    /** Dónde vive el secreto mientras no se ha confirmado. */
    private const SECRETO_PENDIENTE = 'dos_factores.secreto_pendiente';

    public function __construct(private readonly DosFactores $dosFactores)
    {
    }

    /**
     * Pantalla de alta: QR, clave manual y verificación.
     *
     * El secreto se guarda **en la sesión**, no en la base, hasta que se
     * confirma con un código real. Escribirlo antes dejaría cuentas con un
     * segundo factor a medias: activado en la base pero no en ninguna app,
     * o sea la persona fuera de su propia cuenta.
     */
    public function crear(Request $request): Response|RedirectResponse
    {
        $usuario = $request->user();

        if ($usuario->tieneDosFactores()) {
            return redirect()->route('seguridad')
                ->with('info', 'Tu segundo factor ya está activo.');
        }

        $secreto = $request->session()->get(self::SECRETO_PENDIENTE);

        if (! $secreto) {
            $secreto = $this->dosFactores->generarSecreto();
            $request->session()->put(self::SECRETO_PENDIENTE, $secreto);
        }

        return Inertia::render('Auth/TwoFactorSetup', [
            'qr'          => $this->dosFactores->qr($usuario, $secreto),
            'claveManual' => trim(chunk_split($secreto, 4, ' ')),
        ]);
    }

    /**
     * Confirma el código y enciende el segundo factor.
     *
     * @throws ValidationException
     */
    public function confirmar(Request $request): Response|RedirectResponse
    {
        $request->validate(['codigo' => 'required|string']);

        $usuario = $request->user();
        $secreto = $request->session()->get(self::SECRETO_PENDIENTE);

        if (! $secreto) {
            return redirect()->route('dos-factores.crear');
        }

        if (! $this->dosFactores->verificarCodigo($secreto, $request->string('codigo')->toString())) {
            throw ValidationException::withMessages([
                'codigo' => 'Ese código no coincide. Comprueba que el reloj de tu teléfono esté en hora y prueba con el siguiente.',
            ]);
        }

        $usuario->forceFill([
            'dos_factores_secreto'       => $secreto,
            'dos_factores_confirmado_en' => now(),
        ])->save();

        $request->session()->forget(self::SECRETO_PENDIENTE);

        // Activar el segundo factor es un cambio de privilegio.
        $request->session()->regenerate(true);

        $codigos = $this->dosFactores->generarCodigosDeRecuperacion($usuario);

        EventoAutenticacion::registrar(EventoAuth::AltaDosFactores, $usuario);

        // Se devuelve la misma pantalla, ahora con los códigos. Es la **única**
        // vez que existen en claro.
        return Inertia::render('Auth/TwoFactorSetup', [
            'qr'                  => '',
            'claveManual'         => '',
            'codigosRecuperacion' => $codigos,
        ]);
    }

    /** Genera una tanda nueva e invalida la anterior. */
    public function regenerarCodigos(Request $request): Response|RedirectResponse
    {
        $usuario = $request->user();

        if (! $usuario->tieneDosFactores()) {
            return redirect()->route('seguridad');
        }

        $codigos = $this->dosFactores->generarCodigosDeRecuperacion($usuario);

        return Inertia::render('Auth/TwoFactorSetup', [
            'qr'                  => '',
            'claveManual'         => '',
            'codigosRecuperacion' => $codigos,
        ]);
    }

    /**
     * Apaga el segundo factor.
     *
     * Se borran también los códigos de recuperación y los dispositivos de
     * confianza: dejarlos vivos significaría que apagar y volver a encender el
     * segundo factor no limpia nada, y que un código anotado hace meses sigue
     * sirviendo.
     */
    public function destruir(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        if ($this->dosFactores->esObligatorioPara($usuario)) {
            return back()->with('error', 'Tu perfil exige segundo factor, así que no se puede desactivar.');
        }

        $usuario->forceFill([
            'dos_factores_secreto'       => null,
            'dos_factores_confirmado_en' => null,
        ])->save();

        $usuario->codigosRecuperacion()->delete();
        $usuario->dispositivosConfiables()->delete();

        EventoAutenticacion::registrar(EventoAuth::BajaDosFactores, $usuario);

        return back()->with('success', 'Segundo factor desactivado.');
    }
}
