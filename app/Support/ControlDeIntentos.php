<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * B — Límite de intentos de acceso, con dos contadores separados.
 *
 * El límite anterior era `throttle:5,1` sobre la combinación correo+IP. **Todo
 * Nódico sale por la misma IP**: bastaba con que una persona fallara cinco
 * veces desde el coworking para que la clave IP quedara caliente para las
 * demás. Aquí hay dos contadores independientes:
 *
 * - **Por cuenta**, estrecho (5 fallos), porque es lo que de verdad identifica
 *   a quien intenta entrar y es el vector real de fuerza bruta.
 * - **Por IP**, holgado (40 fallos en 15 minutos), pensado para que quepan
 *   varias personas tecleando mal detrás del mismo router sin estorbarse.
 *
 * En vez de un bloqueo seco se aplica **retraso creciente**: la espera se
 * duplica con cada fallo a partir de la tolerancia, hasta un tope de 15
 * minutos. Frena la fuerza bruta hasta hacerla inútil sin dejar a nadie fuera
 * de su cuenta durante una hora por haber tecleado mal.
 *
 * El contador de la cuenta sube **exista o no** ese correo. Si solo subiera
 * para cuentas reales, el propio bloqueo delataría cuáles existen.
 */
class ControlDeIntentos
{
    /** Ventana en la que se acumulan los fallos de una misma cuenta. */
    private const VENTANA_CUENTA = 3600;

    /** Fallos por cuenta antes de que empiece el retraso. */
    private const TOLERANCIA_CUENTA = 5;

    /** Primer retraso de la cuenta, en segundos; se duplica con cada fallo. */
    private const RETRASO_BASE_CUENTA = 5;

    private const VENTANA_IP = 900;

    /** Margen ancho: es la IP compartida de todo el coworking. */
    private const TOLERANCIA_IP = 40;

    private const RETRASO_BASE_IP = 30;

    /** Tope de espera. Más allá de esto el retraso ya no aporta y solo estorba. */
    private const ESPERA_MAXIMA = 900;

    /**
     * Segundos que faltan para poder volver a intentar. 0 si no hay espera.
     *
     * Se devuelve la mayor de las dos esperas: si la cuenta está en retraso y
     * además la IP va caliente, manda la que termine más tarde.
     */
    public function esperaPendiente(string $correo, string $ip): int
    {
        return max(
            $this->esperaDe($this->claveBloqueoCuenta($correo)),
            $this->esperaDe($this->claveBloqueoIp($ip)),
        );
    }

    /**
     * Anota un intento fallido y devuelve los segundos de espera que se acaban
     * de imponer. Devuelve 0 mientras se esté dentro de la tolerancia.
     */
    public function registrarFallo(string $correo, string $ip): int
    {
        $fallosCuenta = $this->incrementar($this->claveFallosCuenta($correo), self::VENTANA_CUENTA);
        $fallosIp     = $this->incrementar($this->claveFallosIp($ip), self::VENTANA_IP);

        $esperaCuenta = $this->calcularEspera($fallosCuenta, self::TOLERANCIA_CUENTA, self::RETRASO_BASE_CUENTA);
        $esperaIp     = $this->calcularEspera($fallosIp, self::TOLERANCIA_IP, self::RETRASO_BASE_IP);

        if ($esperaCuenta > 0) {
            $this->bloquear($this->claveBloqueoCuenta($correo), $esperaCuenta);
        }

        if ($esperaIp > 0) {
            $this->bloquear($this->claveBloqueoIp($ip), $esperaIp);
        }

        return max($esperaCuenta, $esperaIp);
    }

    /** Un acceso correcto limpia el rastro de la cuenta, no el de la IP. */
    public function limpiar(string $correo): void
    {
        Cache::forget($this->claveFallosCuenta($correo));
        Cache::forget($this->claveBloqueoCuenta($correo));
    }

    /** Fallos acumulados por esa cuenta dentro de la ventana. */
    public function fallosDeCuenta(string $correo): int
    {
        return (int) Cache::get($this->claveFallosCuenta($correo), 0);
    }

    /**
     * Marca que ya se avisó por correo de este bloqueo, y dice si era la
     * primera vez. Evita que diez intentos seguidos manden diez correos.
     */
    public function marcarAvisoEnviado(string $correo, int $segundos): bool
    {
        return Cache::add('aviso-bloqueo:' . $this->huella($correo), true, max($segundos, 60));
    }

    // ── Interno ──────────────────────────────────────────────────────────────

    private function calcularEspera(int $fallos, int $tolerancia, int $base): int
    {
        if ($fallos <= $tolerancia) {
            return 0;
        }

        // 1er fallo pasada la tolerancia: `base`. Después se duplica.
        $espera = $base * (2 ** ($fallos - $tolerancia - 1));

        return (int) min($espera, self::ESPERA_MAXIMA);
    }

    private function incrementar(string $clave, int $ventana): int
    {
        // `add` solo escribe si la clave no existía, y es lo que fija el TTL de
        // la ventana. A partir de ahí `increment` cuenta sin reiniciarla.
        Cache::add($clave, 0, $ventana);

        return (int) Cache::increment($clave);
    }

    private function bloquear(string $clave, int $segundos): void
    {
        Cache::put($clave, now()->addSeconds($segundos)->getTimestamp(), $segundos);
    }

    private function esperaDe(string $clave): int
    {
        $hasta = Cache::get($clave);

        if (! $hasta) {
            return 0;
        }

        return max(0, (int) $hasta - now()->getTimestamp());
    }

    /**
     * El correo no se guarda en claro en la caché: la lista de claves de un
     * Redis o de la tabla `cache` sería un padrón de correos de Nódico.
     */
    private function huella(string $correo): string
    {
        return hash('sha256', mb_strtolower(trim($correo)));
    }

    private function claveFallosCuenta(string $correo): string
    {
        return 'intentos:cuenta:' . $this->huella($correo);
    }

    private function claveBloqueoCuenta(string $correo): string
    {
        return 'bloqueo:cuenta:' . $this->huella($correo);
    }

    private function claveFallosIp(string $ip): string
    {
        return 'intentos:ip:' . hash('sha256', $ip);
    }

    private function claveBloqueoIp(string $ip): string
    {
        return 'bloqueo:ip:' . hash('sha256', $ip);
    }
}
