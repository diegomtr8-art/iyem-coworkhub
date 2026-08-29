<?php

namespace App\Listeners;

use App\Enums\EventoAuth;
use App\Models\EventoAutenticacion;
use App\Models\User;
use App\Notifications\AccesoBloqueado;
use App\Support\ControlDeIntentos;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Hash;

/**
 * B — Toda la bitácora de autenticación colgada de los eventos de Laravel, en
 * un solo archivo.
 *
 * Se engancha a los eventos en vez de escribir desde los controladores para
 * que no haya forma de añadir un camino de acceso nuevo —Google en la fase C,
 * enlace mágico, 2FA— y olvidarse de registrarlo: si pasa por el guard, queda
 * anotado.
 */
class RegistrarEventosDeAuth
{
    public function __construct(private readonly ControlDeIntentos $intentos)
    {
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $eventos): array
    {
        return [
            Login::class             => 'alIngresar',
            Failed::class            => 'alFallar',
            Lockout::class           => 'alBloquear',
            Logout::class            => 'alSalir',
            OtherDeviceLogout::class => 'alCerrarOtrosDispositivos',
            Verified::class          => 'alVerificarCorreo',
            PasswordReset::class     => 'alRestablecerContrasena',
        ];
    }

    public function alIngresar(Login $evento): void
    {
        $usuario = $evento->user;

        if ($usuario instanceof User) {
            $this->migrarHashSiHaceFalta($usuario);
        }

        EventoAutenticacion::registrar(
            EventoAuth::IngresoCorrecto,
            $usuario instanceof User ? $usuario : null,
            contexto: ['guard' => $evento->guard, 'recordar' => $evento->remember],
        );
    }

    public function alFallar(Failed $evento): void
    {
        $correo = (string) ($evento->credentials['email'] ?? '');

        EventoAutenticacion::registrar(
            EventoAuth::IngresoFallido,
            $evento->user instanceof User ? $evento->user : null,
            correo: $correo ?: null,
            exito: false,
            contexto: ['fallos_de_la_cuenta' => $this->intentos->fallosDeCuenta($correo)],
        );
    }

    /**
     * El bloqueo se registra y, si el correo corresponde a una cuenta real, se
     * avisa a su titular: puede ser la única señal de que alguien está
     * intentando entrar a su cuenta.
     *
     * El aviso **no** cambia lo que ve quien está intentando entrar. Sale por
     * correo, que es un canal al que el atacante no tiene acceso, así que no
     * abre ninguna vía de enumeración.
     */
    public function alBloquear(Lockout $evento): void
    {
        $correo = (string) $evento->request->input('email', '');
        $ip     = (string) $evento->request->ip();

        $usuario  = $correo !== '' ? User::where('email', $correo)->first() : null;
        $segundos = $this->intentos->esperaPendiente($correo, $ip);

        EventoAutenticacion::registrar(
            EventoAuth::Bloqueo,
            $usuario,
            correo: $correo ?: null,
            exito: false,
            contexto: ['espera_segundos' => $segundos],
        );

        // `marcarAvisoEnviado` devuelve true solo la primera vez de este
        // bloqueo: diez intentos seguidos no deben mandar diez correos.
        if ($usuario && $this->intentos->marcarAvisoEnviado($correo, $segundos)) {
            $usuario->notify(new AccesoBloqueado(
                ip: $ip,
                agente: (string) $evento->request->userAgent(),
                segundos: $segundos,
            ));
        }
    }

    public function alSalir(Logout $evento): void
    {
        if (! $evento->user instanceof User) {
            return;
        }

        EventoAutenticacion::registrar(EventoAuth::CierreSesion, $evento->user);
    }

    public function alCerrarOtrosDispositivos(OtherDeviceLogout $evento): void
    {
        if (! $evento->user instanceof User) {
            return;
        }

        EventoAutenticacion::registrar(EventoAuth::CierreRemoto, $evento->user);
    }

    public function alVerificarCorreo(Verified $evento): void
    {
        if (! $evento->user instanceof User) {
            return;
        }

        EventoAutenticacion::registrar(EventoAuth::CorreoVerificado, $evento->user);
    }

    public function alRestablecerContrasena(PasswordReset $evento): void
    {
        if (! $evento->user instanceof User) {
            return;
        }

        EventoAutenticacion::registrar(EventoAuth::RestablecioContrasena, $evento->user);
    }

    /**
     * B — Migración transparente de hashes viejos.
     *
     * Si el coste de bcrypt sube en `config/hashing.php`, o si algún día se
     * pasa a argon2, las contraseñas ya guardadas se quedarían con el algoritmo
     * antiguo para siempre. El único momento en que la contraseña en claro está
     * disponible para rehacer el hash es justo al iniciar sesión.
     */
    private function migrarHashSiHaceFalta(User $usuario): void
    {
        $hash = (string) $usuario->getAuthPassword();

        if ($hash === '' || ! Hash::needsRehash($hash)) {
            return;
        }

        $enClaro = request()->input('password');

        if (! is_string($enClaro) || $enClaro === '') {
            return;
        }

        // `forceFill` + el cast `hashed` del modelo vuelven a hashear con la
        // configuración vigente.
        $usuario->forceFill(['password' => $enClaro])->save();
    }
}
