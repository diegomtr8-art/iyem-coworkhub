<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EstadoCuenta;
use App\Enums\EventoAuth;
use App\Enums\RolUsuario;
use App\Http\Controllers\Auth\Concerns\ExigeSegundoFactor;
use App\Http\Controllers\Auth\Concerns\RedirigeAlPortal;
use App\Http\Controllers\Controller;
use App\Models\Comunicado;
use App\Models\EventoAutenticacion;
use App\Models\IdentidadSocial;
use App\Models\User;
use App\Servicios\Acceso\IdentidadDeProveedor;
use App\Servicios\Acceso\PerfilExterno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

/**
 * C — Acceso con proveedor externo.
 *
 * Sobre las defensas de OAuth, que es donde se cometen los errores:
 *
 * - **`state`**: Socialite lo genera y lo verifica contra la sesión por
 *   defecto. **Nunca** se usa `stateless()` aquí: es la única defensa contra
 *   CSRF en este flujo y es el fallo más común. Si no coincide, Socialite
 *   lanza `InvalidStateException` y se termina en el login con un mensaje.
 * - **PKCE**: activado con `enablePkce()`. Impide que un código interceptado
 *   sirva sin el verificador que se quedó en la sesión.
 * - **Destino de retorno**: sale de `route()`, nunca de un parámetro de la
 *   petición. Un `redirect_uri` tomado del request es un redirector abierto.
 * - **Proveedor**: se compara contra una lista blanca. Sin eso, el segmento de
 *   la URL elegiría qué driver de Socialite instanciar.
 * - **Correo verificado**: solo se vincula a una cuenta existente si el
 *   proveedor afirma que el correo está verificado. Si no, cualquiera que
 *   registre una cuenta de Google con el correo de otra persona se quedaría
 *   con su cuenta de Nódico.
 */
class OAuthController extends Controller
{
    use ExigeSegundoFactor, RedirigeAlPortal;

    /**
     * Proveedores que este controlador sabe manejar, y su interruptor.
     *
     * Apple **no** está: no se implementó porque `socialiteproviders/apple` no
     * es instalable en este hosting (falta `ext-sodium`) y Nódico todavía no
     * tiene cuenta de Apple Developer. Los pasos están en
     * `docs/AUTH-PROVEEDORES.md`. Un interruptor que encendiera un botón sin
     * flujo detrás sería peor que no tenerlo.
     */
    private const PROVEEDORES = [
        'google' => 'nodico.acceso.google',
    ];

    public function redirigir(Request $request, string $proveedor): RedirectResponse
    {
        $this->asegurarQueEstaEncendido($proveedor);

        return Socialite::driver($proveedor)
            // PKCE: el verificador se queda en la sesión y el código robado no basta.
            ->enablePkce()
            ->redirectUrl(route('oauth.retorno', ['proveedor' => $proveedor]))
            ->redirect();
    }

    public function retorno(Request $request, string $proveedor): RedirectResponse
    {
        $this->asegurarQueEstaEncendido($proveedor);

        // Cancelar a mitad es lo normal, no un error: Google devuelve
        // `error=access_denied` y la persona tiene que acabar en el login con
        // una explicación, no en una excepción.
        if ($request->filled('error')) {
            return redirect()->route('login')->with(
                'status',
                $request->input('error') === 'access_denied'
                    ? 'Cancelaste el acceso con Google. Puedes entrar con tu correo y contraseña.'
                    : 'Google no pudo completar el acceso. Inténtalo de nuevo o entra con tu correo.',
            );
        }

        try {
            $externo = Socialite::driver($proveedor)
                ->enablePkce()
                ->redirectUrl(route('oauth.retorno', ['proveedor' => $proveedor]))
                ->user();
        } catch (InvalidStateException $e) {
            // `state` que no cuadra: o la sesión caducó, o alguien intentó
            // colar un retorno ajeno. En los dos casos se corta aquí.
            EventoAutenticacion::registrar(
                EventoAuth::IngresoFallido,
                exito: false,
                contexto: ['proveedor' => $proveedor, 'motivo' => 'state_invalido'],
            );

            return redirect()->route('login')->with(
                'status',
                'La sesión de acceso caducó. Vuelve a intentarlo.',
            );
        } catch (Throwable $e) {
            Log::error('Fallo en el retorno de OAuth.', [
                'proveedor' => $proveedor,
                'motivo'    => $e->getMessage(),
            ]);

            return redirect()->route('login')->with(
                'status',
                'No pudimos completar el acceso con Google. Inténtalo de nuevo o entra con tu correo.',
            );
        }

        $correo = Str::lower(trim((string) $externo->getEmail()));

        if ($correo === '') {
            return redirect()->route('login')->with(
                'status',
                'Google no nos compartió tu correo, y lo necesitamos para crear tu cuenta.',
            );
        }

        $bruto = (array) ($externo->user ?? []);

        $usuario = app(IdentidadDeProveedor::class)->resolver($proveedor, new PerfilExterno(
            id: (string) $externo->getId(),
            correo: $correo,
            nombre: $externo->getName(),
            avatar: $externo->getAvatar(),
            // Google entrega `email_verified` en el token; Socialite lo deja en
            // `user`. Ante la duda se responde **no**: es la respuesta segura.
            correoVerificado: ($bruto['email_verified'] ?? false) === true
                || ($bruto['verified_email'] ?? false) === true,
        ));

        if ($usuario === null) {
            return redirect()->route('login')->with(
                'status',
                'Esa cuenta de Google no tiene el correo verificado, así que no podemos vincularla a una cuenta que ya existe en Nódico. Entra con tu contraseña.',
            );
        }

        // D — Entrar con Google tampoco rodea el segundo factor.
        if ($this->necesitaSegundoFactor($usuario, $request)) {
            return $this->mandarAlDesafio($usuario, $request);
        }

        Auth::login($usuario, remember: true);

        // Cambio de privilegio: identificador de sesión nuevo, destruyendo el
        // anterior. Ver `AuthenticatedSessionController`.
        $request->session()->regenerate(true);

        return $this->alPortal($usuario);
    }

    /**
     * Lista blanca y apagado real.
     *
     * Con el proveedor apagado la ruta responde **404**, no un 403: para quien
     * pruebe la URL, ese camino sencillamente no existe. Esconder el botón en
     * el front no es control de acceso.
     */
    private function asegurarQueEstaEncendido(string $proveedor): void
    {
        $clave = self::PROVEEDORES[$proveedor] ?? null;

        abort_if($clave === null, 404);
        abort_unless((bool) config($clave), 404);
    }
}
