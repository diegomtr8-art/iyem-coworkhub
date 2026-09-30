<?php

namespace App\Servicios\Sitio;

use Illuminate\Support\Facades\Http;

/**
 * Comprueba que un enlace del sitio responde, para avisar al guardar. No
 * bloquea: un sitio caído un rato no debería impedir cambiar un texto. Pero un
 * enlace roto en el pie está roto para todos, y quien lo pone tiene que saberlo.
 *
 * Es una petición que hace el servidor a una URL que escribe alguien, así que
 * solo va a hosts públicos: nada de localhost, redes internas ni la IP de
 * metadatos de la nube. Sin esto, el campo «enlace» serviría para sondear la
 * red del servidor.
 */
class ComprobadorDeEnlaces
{
    private const SEGUNDOS = 5;

    /** Si responde con algo que no sea error. */
    public function responde(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $ip = is_string($host) ? $this->ipPublica($host) : null;

        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || $ip === null) {
            return false;
        }

        // La petición va a la IP ya comprobada: si el DNS cambiara entre la
        // comprobación y la petición (rebinding), no acabaría en la red interna.
        $puerto = parse_url($url, PHP_URL_PORT) ?: 443;
        $cliente = fn () => Http::timeout(self::SEGUNDOS)
            ->withoutRedirecting()
            ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$host}:{$puerto}:{$ip}"]]]);

        try {
            // Algunos sitios no contestan a HEAD; con un GET basta mirar el estado.
            $respuesta = $cliente()->head($url);

            if ($respuesta->status() === 405 || $respuesta->status() === 403) {
                $respuesta = $cliente()->get($url);
            }

            // Una redirección cuenta como viva: los acortadores (maps.app.goo.gl)
            // responden así siempre.
            return $respuesta->status() < 400;
        } catch (\Throwable) {
            return false;
        }
    }

    /** La primera IP del host si todas son públicas; si alguna no lo es, nada. */
    protected function ipPublica(string $host): ?string
    {
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        return $ips[0] ?? null;
    }
}
