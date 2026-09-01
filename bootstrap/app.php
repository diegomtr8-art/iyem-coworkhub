<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Fase 2 — endpoints del agente de acceso. Van fuera del grupo `web`
            // (sin sesión ni CSRF): son servicio-a-servicio y se autentican con la
            // firma HMAC del agente, no con una cookie. El prefijo `api/acceso`
            // reproduce la URL a la que el agente ya publica.
            Route::middleware(\App\Http\Middleware\VerificaFirmaDelAgente::class)
                ->prefix('api/acceso')
                ->name('acceso.')
                ->group(__DIR__.'/../routes/acceso.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // Fase 4.A — el webhook de Stripe no trae token CSRF (lo manda Stripe,
        // no un navegador). Su autenticidad se comprueba con la firma del
        // webhook, no con CSRF.
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);

        $middleware->web(append: [
            // B — Va la primera para que las cabeceras salgan tambien en las
            // respuestas de error, que es cuando mas falta hacen.
            \App\Http\Middleware\CabecerasDeSeguridad::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // A.1 — `admin` y `miembro` se fusionan en uno solo, parametrizado por
        // portal, que aborta en vez de redirigir. Ver `PerteneceAlPortal`.
        $middleware->alias([
            'portal'       => \App\Http\Middleware\PerteneceAlPortal::class,
            'inactividad'  => \App\Http\Middleware\CaducidadPorInactividad::class,
            'no.suspendida' => \App\Http\Middleware\CuentaNoSuspendida::class,
            'consentimiento' => \App\Http\Middleware\ConsentimientoVigente::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * A.1 — El 403 es ahora el destino de todo rol que no encaja en el
         * portal que pidio, asi que no puede ser la pantalla en blanco de
         * Symfony: tiene que explicar que paso y ofrecer una salida.
         *
         * Se envuelve solo el 403. El resto de codigos siguen con el manejo por
         * defecto, incluida la pagina de depuracion en local.
         */
        $exceptions->respond(function (Response $respuesta, Throwable $e, Request $peticion) {
            if ($respuesta->getStatusCode() !== 403 || $peticion->expectsJson()) {
                return $respuesta;
            }

            $mensaje = trim((string) $e->getMessage());

            // Gate y Policy lanzan el texto en ingles de Laravel; el usuario no
            // deberia verlo nunca.
            if ($mensaje === '' || $mensaje === 'This action is unauthorized.') {
                $mensaje = 'No tienes permiso para entrar a esta sección de Nódico.';
            }

            return Inertia::render('Errors/403', ['mensaje' => $mensaje])
                ->toResponse($peticion)
                ->setStatusCode(403);
        });
    })->create();
