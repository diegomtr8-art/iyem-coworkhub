<?php

namespace App\Servicios\Acceso;

use App\Enums\EstadoCuenta;
use App\Enums\EventoAuth;
use App\Enums\RolUsuario;
use App\Models\Comunicado;
use App\Models\EventoAutenticacion;
use App\Models\IdentidadSocial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Qué cuenta de Nódico corresponde a una identidad externa (Google).
 *
 * Sacado de `OAuthController` el 22/09/2026 para que la app —que verifica un
 * `id_token` en vez de pasar por Socialite— resuelva exactamente igual: mismas
 * reglas de vinculación, misma defensa contra el robo de cuentas por correo.
 */
class IdentidadDeProveedor
{
    /**
     * Devuelve la cuenta con la que iniciar sesión, o `null` si no se puede
     * vincular con seguridad.
     */
    public function resolver(string $proveedor, PerfilExterno $externo): ?User
    {
        // 1. Esta identidad ya estaba vinculada: es la vía normal.
        $identidad = IdentidadSocial::where('proveedor', $proveedor)
            ->where('proveedor_id', $externo->id)
            ->first();

        if ($identidad) {
            $identidad->update([
                'correo'           => $externo->correo,
                'avatar'           => $externo->avatar,
                'ultimo_acceso_en' => now(),
            ]);

            return $identidad->usuario;
        }

        $existente = User::where('email', $externo->correo)->first();

        // 2. Ya hay una cuenta con ese correo: se vincula, **solo** si el
        //    proveedor afirma que el correo está verificado. Sin esa
        //    comprobación, registrar una cuenta de Google con el correo de
        //    otra persona sería suficiente para quedarse con su cuenta.
        if ($existente) {
            if (! $externo->correoVerificado) {
                return null;
            }

            $this->vincular($existente, $proveedor, $externo);

            return $existente;
        }

        // 3. Nadie con ese correo: se crea la cuenta.
        return $this->crear($proveedor, $externo);
    }

    private function vincular(User $usuario, string $proveedor, PerfilExterno $externo): void
    {
        // `create` y no `updateOrCreate`: el único compuesto de la tabla es lo
        // que impide que dos cuentas reclamen la misma identidad, y quiero que
        // salte si algo intenta saltárselo.
        IdentidadSocial::create([
            'user_id'          => $usuario->id,
            'proveedor'        => $proveedor,
            'proveedor_id'     => $externo->id,
            'correo'           => $externo->correo,
            'avatar'           => $externo->avatar,
            'ultimo_acceso_en' => now(),
        ]);

        EventoAutenticacion::registrar(
            EventoAuth::VinculoSocial,
            $usuario,
            contexto: ['proveedor' => $proveedor],
        );
    }

    private function crear(string $proveedor, PerfilExterno $externo): User
    {
        return DB::transaction(function () use ($proveedor, $externo) {
            $usuario = new User();

            $usuario->fill([
                'name'  => (string) ($externo->nombre ?: Str::before($externo->correo, '@')),
                'email' => $externo->correo,
            ]);

            $usuario->forceFill([
                'tipo'          => RolUsuario::Miembro->value,
                'estado_cuenta' => EstadoCuenta::Pendiente->value,
                'face_id_ok'    => false,
                // Quien entra con Google queda verificado: Google ya comprobó
                // esa dirección. Si el proveedor no lo afirma, no se da por hecho.
                'email_verified_at' => $externo->correoVerificado ? now() : null,
                // Sin contraseña. Se le ofrece ponerla desde el perfil, sin
                // obligarlo; mientras tanto su acceso es la identidad externa.
                'password' => null,
            ])->save();

            Comunicado::create([
                'user_id' => $usuario->id,
                'titulo'  => '¡Bienvenido a Nódico, ' . explode(' ', $usuario->name)[0] . '!',
                'mensaje' => 'Entraste con tu cuenta de ' . ucfirst($proveedor) . '. Cuando contrates un plan o pases por recepción, activamos tu membresía y el registro de Face ID.',
                'tipo'    => 'bienvenida',
                'leido'   => false,
            ]);

            $this->vincular($usuario, $proveedor, $externo);

            return $usuario;
        });
    }
}
