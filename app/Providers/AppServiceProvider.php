<?php

namespace App\Providers;

use App\Listeners\RegistrarEventosDeAuth;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fase 4.A — el webhook de Stripe lo atiende nuestro StripeWebhookController
        // (Cashier + lógica de Nódico), registrado a mano en routes/web.php. Se
        // desactiva el auto-registro de Cashier para no tener dos rutas al mismo
        // path, una apuntando a su controller «pelado».
        \Laravel\Cashier\Cashier::ignoreRoutes();
    }

    public function boot(): void
    {
        $this->forzarHttps();
        $this->definirReglasDeContrasena();
        $this->limitarContacto();
        $this->limitarElAcceso();
        $this->marcarLosCorreosDeAcceso();
        $this->llevarACadaQuienASuPortal();

        Event::subscribe(RegistrarEventosDeAuth::class);

        Vite::prefetch(concurrency: 3);
    }

    /**
     * B — HTTPS obligatorio fuera de local.
     *
     * Con `trustProxies(at: '*')` el esquema lo decide `X-Forwarded-Proto`, así
     * que basta con que un salto intermedio hable HTTP para que todas las URL
     * generadas —incluido el enlace firmado de verificación— salgan en claro.
     */
    private function forzarHttps(): void
    {
        if ($this->app->environment('local', 'testing')) {
            return;
        }

        URL::forceScheme('https');
    }

    /**
     * B — Reglas de contraseña, en un solo sitio.
     *
     * `Password::defaults()` lo usan el registro, el restablecimiento y el
     * cambio desde el perfil, así que la regla no puede divergir entre
     * pantallas.
     *
     * Mínimo 10 caracteres con letras y números, y `uncompromised()`, que
     * consulta Have I Been Pwned por k-anonimato: se envían **cinco caracteres
     * del hash SHA-1**, nunca la contraseña. Es la medida con mejor relación
     * esfuerzo/beneficio que existe.
     *
     * **No** se exigen símbolos ni caducidad periódica: el NIST lo desaconseja
     * desde 2017 (SP 800-63B) porque empuja a la gente a `Verano2026!` y a
     * reutilizar. Un mínimo largo y no filtrada protege mucho más.
     *
     * Si la API de HIBP no responde, la contraseña **pasa**: es el
     * comportamiento por defecto de Laravel y es la decisión tomada. Preferimos
     * una ventana sin comprobar a un registro inservible por una red ajena.
     */
    private function definirReglasDeContrasena(): void
    {
        Password::defaults(function () {
            $regla = Password::min(10)->letters()->numbers();

            // En pruebas la comprobación saldría a internet en cada `create()`.
            return $this->app->environment('testing')
                ? $regla
                : $regla->uncompromised();
        });
    }

    /**
     * BE-02 — el límite anterior era `throttle:5,1` por IP, y todo el coworking
     * sale por la misma: cinco envíos en un minuto desde el propio espacio
     * bloqueaban a los demás. Se combina un margen amplio por IP con uno
     * estrecho por correo, que es lo que de verdad identifica a quien envía.
     */
    private function limitarContacto(): void
    {
        RateLimiter::for('contacto', function (Request $request) {
            $correo = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinutes(10, 30)->by('ip:' . $request->ip()),
                Limit::perMinutes(10, 3)->by('correo:' . ($correo ?: $request->ip())),
            ];
        });
    }

    /**
     * B — Los correos de acceso salen con la identidad de Nódico, no con la
     * plantilla gris de Laravel.
     *
     * Se usan los ganchos `toMailUsing()` en vez de sustituir las clases de
     * notificación: así `VerifyEmail` y `ResetPassword` siguen siendo las de
     * Laravel —y todo lo que las espera sigue funcionando— pero el mensaje es
     * enteramente nuestro.
     */
    private function marcarLosCorreosDeAcceso(): void
    {
        VerifyEmail::toMailUsing(function (object $notificable, string $url): MailMessage {
            return (new MailMessage)
                ->subject('Confirma tu correo — Nódico')
                ->view('emails.verificar-correo', [
                    'nombre'  => $this->primerNombre($notificable),
                    'url'     => $url,
                    'minutos' => (int) config('auth.verification.expire', 60),
                ]);
        });

        ResetPassword::toMailUsing(function (object $notificable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notificable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject('Restablece tu contraseña — Nódico')
                ->view('emails.restablecer-contrasena', [
                    'nombre'  => $this->primerNombre($notificable),
                    'url'     => $url,
                    'minutos' => (int) config('auth.passwords.users.expire', 60),
                ]);
        });
    }

    /**
     * B — Topes gruesos de las rutas de acceso.
     *
     * Esto **no** es el límite de intentos de contraseña: ese vive en
     * `App\Support\ControlDeIntentos`, es por cuenta y por IP a la vez, y
     * aplica retraso creciente. Lo de aquí es la red de abajo: impedir que
     * alguien inunde el formulario de registro para usarlo como generador de
     * correos, o martillee la recuperación.
     *
     * Todos los límites por IP van holgados **a propósito**: todo Nódico sale
     * por la misma IP y un margen estrecho dejaría fuera a la sala entera.
     */
    private function limitarElAcceso(): void
    {
        RateLimiter::for('acceso', fn (Request $request) => [
            Limit::perMinute(60)->by('ip:' . $request->ip()),
        ]);

        RateLimiter::for('registro', fn (Request $request) => [
            Limit::perHour(20)->by('ip:' . $request->ip()),
            // Sin esto, el alta manda un correo a cualquier dirección tantas
            // veces como se pida: un aviso de «ya tienes cuenta» convertido en
            // herramienta de acoso.
            Limit::perHour(3)->by('correo:' . $this->correoDe($request)),
        ]);

        RateLimiter::for('recuperacion', fn (Request $request) => [
            Limit::perHour(30)->by('ip:' . $request->ip()),
            Limit::perHour(5)->by('correo:' . $this->correoDe($request)),
        ]);

        RateLimiter::for('reenvio', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by('usuario:' . ($request->user()?->id ?: $request->ip())),
        ]);
    }

    private function correoDe(Request $request): string
    {
        $correo = Str::lower(trim((string) $request->input('email')));

        return $correo !== '' ? $correo : 'ip:' . $request->ip();
    }

    /**
     * A.1 — Quien ya tiene sesión y vuelve a /login va a **su** portal.
     *
     * `RedirectIfAuthenticated` manda a `/dashboard` de fábrica, así que un
     * miembro que abriera /login estando dentro acababa en el panel operativo:
     * antes, rebotando; hoy, con un 403 que no viene a cuento. Y si el rol no
     * se reconoce, a la portada: no hay portal al que mandarlo.
     */
    private function llevarACadaQuienASuPortal(): void
    {
        RedirectIfAuthenticated::redirectUsing(function (Request $request): string {
            $ruta = $request->user()?->rutaInicio();

            return $ruta ? route($ruta) : route('home');
        });
    }

    private function primerNombre(object $notificable): string
    {
        $nombre = trim((string) ($notificable->name ?? ''));

        return $nombre === '' ? 'Hola' : explode(' ', $nombre)[0];
    }
}
