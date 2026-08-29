<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * B — Cabeceras de seguridad en todas las respuestas.
 *
 * **Sobre la CSP:** una política estricta rompe el sitio en silencio —el
 * navegador bloquea el recurso y no avisa a nadie— y este sitio incrusta
 * YouTube, Instagram, Luma y Google Maps. Por eso arranca en modo
 * `Report-Only`, que registra lo que habría bloqueado sin bloquearlo. Cuando
 * `NODICO_CSP_ESTRICTA=true` pasa a aplicarse de verdad.
 *
 * Ese interruptor es el único paso que queda para cerrarla; hasta que alguien
 * revise los informes en staging, aplicarla a ciegas sería cambiar un riesgo
 * conocido por una portada rota.
 */
class CabecerasDeSeguridad
{
    /** Orígenes que el sitio ya usa hoy. Ver `config/nodico.php` y `Welcome.vue`. */
    private const ORIGENES = [
        'youtube'   => ['https://www.youtube.com', 'https://www.youtube-nocookie.com', 'https://i.ytimg.com'],
        'instagram' => ['https://www.instagram.com', 'https://*.cdninstagram.com', 'https://*.fbcdn.net'],
        'luma'      => ['https://luma.com', 'https://*.luma.com', 'https://lu.ma'],
        'maps'      => ['https://maps.google.com', 'https://www.google.com', 'https://*.googleapis.com', 'https://*.gstatic.com'],
        'fuentes'   => ['https://fonts.googleapis.com', 'https://fonts.gstatic.com'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(24);
        $request->attributes->set('csp_nonce', $nonce);

        /** @var Response $respuesta */
        $respuesta = $next($request);

        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $respuesta->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Nada de esto lo usa el sitio; se apaga por si acaso.
        $respuesta->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()'
        );

        // HSTS solo tiene sentido —y solo es seguro— sobre HTTPS real. Anunciarlo
        // en local dejaría el navegador del equipo forzando https://localhost
        // durante un año.
        if ($request->secure() && ! app()->environment('local', 'testing')) {
            $respuesta->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        $cabeceraCsp = config('nodico.csp_estricta')
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';

        $respuesta->headers->set($cabeceraCsp, $this->politica($nonce));

        return $respuesta;
    }

    private function politica(string $nonce): string
    {
        $marcos  = array_merge(self::ORIGENES['youtube'], self::ORIGENES['luma'], self::ORIGENES['maps'], self::ORIGENES['instagram']);
        $imagenes = array_merge(self::ORIGENES['youtube'], self::ORIGENES['instagram'], self::ORIGENES['maps']);

        $directivas = [
            "default-src 'self'",
            // `unsafe-inline` va **detrás** del nonce a propósito: los navegadores
            // modernos lo ignoran cuando hay nonce, y los viejos que no entienden
            // nonce se quedan con él en vez de romper la página.
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-inline' " . implode(' ', array_merge(self::ORIGENES['youtube'], self::ORIGENES['luma'])),
            // Tailwind y los estilos en línea de los componentes obligan a
            // `unsafe-inline` en estilos. Es un riesgo bajo comparado con el de
            // scripts, y quitarlo exigiría reescribir el front entero.
            "style-src 'self' 'unsafe-inline' " . implode(' ', self::ORIGENES['fuentes']),
            'font-src \'self\' data: ' . implode(' ', self::ORIGENES['fuentes']),
            "img-src 'self' data: blob: " . implode(' ', $imagenes),
            "frame-src 'self' " . implode(' ', $marcos),
            "connect-src 'self' " . implode(' ', self::ORIGENES['maps']),
            "media-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            // Nadie debe poder enviar los formularios de Nódico a otro sitio.
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        return implode('; ', $directivas);
    }
}
