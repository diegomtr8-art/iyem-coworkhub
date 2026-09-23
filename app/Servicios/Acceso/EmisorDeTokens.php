<?php

namespace App\Servicios\Acceso;

use App\Enums\EventoAuth;
use App\Exceptions\ErrorDeApi;
use App\Http\Middleware\Movil\CaraDelToken;
use App\Http\Resources\Movil\UsuarioMovil;
use App\Models\EventoAutenticacion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Emite los tokens de la app y los desafíos de segundo factor.
 *
 * Todos los caminos de acceso de la app —contraseña, Google, enlace mágico—
 * terminan aquí, para que ninguno se salte una regla que los demás cumplen:
 *
 * - **Solo dos caras.** Miembro, o cuenta del equipo con `ver-reportes`. Staff y
 *   caja no reciben token: su trabajo es en el panel web.
 * - **El segundo factor no se rodea.** Con 2FA activo se emite un desafío de un
 *   solo uso, que vive 5 minutos y no da acceso a nada por sí mismo.
 * - **Un token por teléfono.** Volver a entrar desde el mismo `dispositivo_id`
 *   revoca el anterior de ese teléfono.
 */
class EmisorDeTokens
{
    public const MINUTOS_DEL_DESAFIO = 5;

    /**
     * @param  array{dispositivo: string, dispositivo_id: string, plataforma?: ?string}  $dispositivo
     * @return array<string, mixed>  el cuerpo de `data` de la respuesta
     *
     * @throws ErrorDeApi
     */
    public function entrar(User $usuario, array $dispositivo, string $via): array
    {
        $cara = $this->caraDe($usuario);

        if ($usuario->tieneDosFactores()) {
            return $this->desafiar($usuario, $dispositivo, $via);
        }

        return $this->emitir($usuario, $cara, $dispositivo, $via);
    }

    /** Códigos incorrectos que aguanta un desafío antes de quemarse. */
    public const INTENTOS_POR_DESAFIO = 5;

    /** Códigos incorrectos por cuenta en la ventana, sumando todos sus desafíos. */
    public const INTENTOS_POR_CUENTA = 10;

    public const MINUTOS_VENTANA_CUENTA = 15;

    /**
     * Segundo paso: canjea el desafío si `$verificar` acepta el código.
     *
     * Tres defensas contra adivinar el código de 6 dígitos:
     *
     * - **Atómico.** Todo ocurre bajo un candado por desafío: dos peticiones
     *   simultáneas con el código correcto ya no reciben dos tokens.
     * - **El desafío se quema** tras 5 códigos incorrectos: hay que volver a
     *   poner la contraseña.
     * - **Límite por cuenta** que sobrevive a los desafíos nuevos: entrar con
     *   la contraseña correcta limpia el retraso del login, así que sin esto
     *   bastaba pedir otro desafío para seguir probando.
     *
     * @param  callable(User): bool  $verificar
     * @return array{usuario: User, fallo: bool, dispositivo?: array, via?: string}
     *
     * @throws ErrorDeApi
     */
    public function canjearDesafio(string $desafio, callable $verificar): array
    {
        $clave = $this->claveDelDesafio($desafio);

        return Cache::lock($clave . ':candado', 10)->block(5, function () use ($clave, $verificar) {
            $pendiente = Cache::get($clave);
            $usuario   = is_array($pendiente) ? User::find($pendiente['user_id']) : null;

            // Se relee de la base: si entre un paso y otro se apagó el segundo
            // factor, tiene que notarse aquí.
            if (! $usuario || ! $usuario->tieneDosFactores()) {
                Cache::forget($clave);

                throw new ErrorDeApi(401, 'desafio_caducado', 'La verificación caducó. Vuelve a entrar con tu contraseña.');
            }

            $limite = 'movil:2fa:cuenta:' . $usuario->id;

            if (RateLimiter::tooManyAttempts($limite, self::INTENTOS_POR_CUENTA)) {
                Cache::forget($clave);

                throw new ErrorDeApi(429, 'demasiadas_peticiones', 'Demasiados códigos incorrectos. Espera unos minutos y vuelve a entrar.', [
                    'reintentar_en' => RateLimiter::availableIn($limite),
                ]);
            }

            if (! $verificar($usuario)) {
                RateLimiter::hit($limite, self::MINUTOS_VENTANA_CUENTA * 60);
                $intentos = (int) ($pendiente['intentos'] ?? 0) + 1;

                if ($intentos >= self::INTENTOS_POR_DESAFIO) {
                    Cache::forget($clave);

                    throw new ErrorDeApi(401, 'desafio_caducado', 'Demasiados códigos incorrectos. Vuelve a entrar con tu contraseña.');
                }

                // Se conserva la caducidad original: fallar no alarga la vida.
                Cache::put($clave, [...$pendiente, 'intentos' => $intentos], CarbonImmutable::createFromTimestamp($pendiente['caduca']));

                return ['usuario' => $usuario, 'fallo' => true];
            }

            // Se gasta **antes** de salir del candado: el segundo que llegue ya
            // no lo encuentra.
            Cache::forget($clave);
            RateLimiter::clear($limite);

            return ['usuario' => $usuario, 'dispositivo' => $pendiente['dispositivo'], 'via' => $pendiente['via'], 'fallo' => false];
        });
    }

    /**
     * @param  array{dispositivo: string, dispositivo_id: string, plataforma?: ?string}  $dispositivo
     */
    public function emitir(User $usuario, string $cara, array $dispositivo, string $via): array
    {
        // Mismo teléfono, token nuevo: el anterior sobra y confundiría la lista
        // de «Mi seguridad».
        $usuario->tokens()->where('dispositivo_id', $dispositivo['dispositivo_id'])->delete();

        $nuevo = $usuario->createToken(Str::limit($dispositivo['dispositivo'], 120, ''), [$cara]);

        $nuevo->accessToken->forceFill([
            'dispositivo_id' => $dispositivo['dispositivo_id'],
            'plataforma'     => $dispositivo['plataforma'] ?? null,
        ])->save();

        EventoAutenticacion::registrar(
            EventoAuth::IngresoCorrecto,
            $usuario,
            contexto: ['via' => $via, 'app' => true, 'dispositivo' => $dispositivo['dispositivo']],
        );

        return [
            'token' => $nuevo->plainTextToken,
            'caduca_por_inactividad_en_dias' => (int) config("nodico.app_movil.dias_inactividad.{$cara}"),
            'usuario' => (new UsuarioMovil($usuario))->resolve(),
        ];
    }

    /** @throws ErrorDeApi */
    public function caraDe(User $usuario): string
    {
        $cara = CaraDelToken::paraUsuario($usuario);

        if ($cara === null) {
            throw new ErrorDeApi(403, 'cuenta_operativa', 'La app es para miembros de Nódico. Tu trabajo se hace desde el panel web.');
        }

        return $cara;
    }

    private function desafiar(User $usuario, array $dispositivo, string $via): array
    {
        $desafio = 'd_' . Str::random(48);
        $caduca  = now()->addMinutes(self::MINUTOS_DEL_DESAFIO);

        Cache::put($this->claveDelDesafio($desafio), [
            'user_id'     => $usuario->id,
            'dispositivo' => $dispositivo,
            'via'         => $via,
            'intentos'    => 0,
            'caduca'      => $caduca->getTimestamp(),
        ], $caduca);

        return [
            'requiere_dos_factores' => true,
            'desafio'               => $desafio,
            'caduca_en'             => $caduca->toIso8601String(),
        ];
    }

    private function claveDelDesafio(string $desafio): string
    {
        // Se guarda su hash: la caché no es sitio para credenciales en claro.
        return 'movil:2fa:' . hash('sha256', $desafio);
    }
}
