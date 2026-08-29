<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EventoAuth;
use App\Http\Controllers\Auth\Concerns\ExigeSegundoFactor;
use App\Http\Controllers\Auth\Concerns\RedirigeAlPortal;
use App\Http\Controllers\Controller;
use App\Models\EnlaceMagico;
use App\Models\EventoAutenticacion;
use App\Models\User;
use App\Notifications\EnlaceDeAcceso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * C — Entrar con un enlace de un solo uso, sin contraseña.
 *
 * Es el proveedor que más sentido tiene después de Google: cero costo, cero
 * dependencia de terceros y muy buena conversión en móvil, que es donde está
 * el público de Nódico.
 *
 * Las tres defensas que lo hacen aceptable:
 *
 * 1. **Un solo uso.** El token se marca al gastarse; el segundo intento con el
 *    mismo enlace no entra.
 * 2. **15 minutos.** Es una credencial completa que viaja por correo: cuanto
 *    menos viva, mejor.
 * 3. **Atado al navegador que lo pidió.** Al solicitarlo queda un secreto en
 *    la sesión, y el enlace solo funciona desde ahí. Sin esto, un enlace
 *    reenviado o leído desde otro dispositivo sirve igual — que es la objeción
 *    clásica a este mecanismo.
 *
 * Y como todo lo de la fase B: la respuesta es idéntica exista o no la cuenta.
 */
class EnlaceMagicoController extends Controller
{
    use ExigeSegundoFactor, RedirigeAlPortal;

    /** Clave del secreto que ata el enlace a este navegador. */
    private const CLAVE_SESION = 'enlace_magico_secreto';

    public function enviar(Request $request): RedirectResponse
    {
        $this->asegurarQueEstaEncendido();

        $datos = $request->validate(['email' => 'required|string|email|max:255']);

        $correo  = Str::lower(trim($datos['email']));
        $usuario = User::where('email', $correo)->first();

        if ($usuario) {
            $this->emitirYEnviar($request, $usuario);
        }

        EnlaceMagico::limpiarCaducados();

        // Exista o no la cuenta, misma pantalla y mismo texto.
        return redirect()
            ->route('enlace-magico.enviado')
            ->with('correo', $correo);
    }

    public function enviado(Request $request): Response|RedirectResponse
    {
        $this->asegurarQueEstaEncendido();

        $correo = $request->session()->get('correo');

        if (! $correo) {
            return redirect()->route('login');
        }

        // Se conserva para el reenvío desde la propia pantalla.
        $request->session()->keep(['correo', self::CLAVE_SESION]);

        return Inertia::render('Auth/MagicLinkSent', [
            'correo'    => $correo,
            'reenviado' => (bool) $request->session()->get('reenviado', false),
        ]);
    }

    public function entrar(Request $request, string $token): RedirectResponse
    {
        $this->asegurarQueEstaEncendido();

        $enlace = EnlaceMagico::with('usuario')->conToken($token)->first();

        if (! $enlace || ! $enlace->vigente()) {
            return redirect()->route('login')->with(
                'status',
                'Ese enlace ya no sirve: caducó o ya se usó. Pide uno nuevo.',
            );
        }

        if (! $enlace->coincideLaHuella($request->session()->get(self::CLAVE_SESION))) {
            EventoAutenticacion::registrar(
                EventoAuth::IngresoFallido,
                $enlace->usuario,
                exito: false,
                contexto: ['motivo' => 'enlace_magico_otro_navegador'],
            );

            return redirect()->route('login')->with(
                'status',
                'Ese enlace se pidió desde otro navegador, así que aquí no funciona. Pide uno nuevo desde este dispositivo.',
            );
        }

        // Se marca gastado **antes** de abrir la sesión: si algo fallara
        // después, el enlace ya no vale para un segundo intento.
        $enlace->forceFill(['usado_en' => now()])->save();

        $usuario = $enlace->usuario;

        // D — Un enlace magico **no** rodea el segundo factor. Si lo hiciera,
        // bastaria con tener acceso al buzon para saltarselo, y el segundo
        // factor dejaria de serlo.
        if ($this->necesitaSegundoFactor($usuario, $request)) {
            $request->session()->forget(self::CLAVE_SESION);

            return $this->mandarAlDesafio($usuario, $request);
        }

        Auth::login($usuario, remember: true);
        $request->session()->forget(self::CLAVE_SESION);
        $request->session()->regenerate(true);

        EventoAutenticacion::registrar(
            EventoAuth::IngresoCorrecto,
            $usuario,
            contexto: ['via' => 'enlace_magico'],
        );

        return $this->alPortal($usuario);
    }

    private function emitirYEnviar(Request $request, User $usuario): void
    {
        $emitido = EnlaceMagico::emitir($usuario, $request->ip());

        // El secreto queda solo aquí: es lo que ata el enlace a este navegador.
        $request->session()->put(self::CLAVE_SESION, $emitido['secreto']);

        try {
            $usuario->notify(new EnlaceDeAcceso($emitido['token']));
        } catch (Throwable $e) {
            // Igual que en el registro: un fallo del correo no puede tumbar la
            // peticion ni cambiar lo que ve quien lo pidio.
            Log::error('No se pudo enviar el enlace magico.', [
                'usuario' => $usuario->id,
                'motivo'  => $e->getMessage(),
            ]);
        }
    }

    private function asegurarQueEstaEncendido(): void
    {
        abort_unless((bool) config('nodico.acceso.enlace_magico'), 404);
    }
}
