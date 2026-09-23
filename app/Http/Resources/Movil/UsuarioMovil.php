<?php

namespace App\Http\Resources\Movil;

use App\Http\Middleware\Movil\CaraDelToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * La persona del token, tal como la ve la app. Solo lo suyo: nunca notas del
 * equipo, ni secretos, ni datos de facturación.
 *
 * @mixin User
 */
class UsuarioMovil extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'nombre'            => $this->name,
            'email'             => $this->email,
            'correo_verificado' => $this->hasVerifiedEmail(),
            'avatar_url'        => self::urlPublica($this->avatar),
            'telefono'          => $this->telefono,
            'empresa'           => $this->empresa,
            'ocupacion'         => $this->ocupacion,
            'face_id_ok'        => (bool) $this->face_id_ok,
            'dos_factores_activo' => $this->tieneDosFactores(),
            'tiene_contrasena'  => $this->tieneContrasena(),

            // Qué cara de la app le toca: el portal o los reportes.
            'cara'              => CaraDelToken::paraUsuario($this->resource),

            'contacto_emergencia' => [
                'nombre'     => $this->contacto_emergencia_nombre,
                'telefono'   => $this->contacto_emergencia_telefono,
                'parentesco' => $this->contacto_emergencia_parentesco,
            ],

            'preferencias' => [
                'reservas'  => (bool) $this->notif_reservas,
                'membresia' => (bool) $this->notif_membresia,
                'comunidad' => (bool) $this->notif_comunidad,
            ],
        ];
    }

    /**
     * URL absoluta de un archivo del disco público, armada con la raíz de la
     * petición: el teléfono no llega a `localhost`, llega a la IP o al dominio
     * por el que hizo la llamada.
     */
    public static function urlPublica(?string $ruta): ?string
    {
        if (blank($ruta)) {
            return null;
        }

        if (str_starts_with($ruta, 'http://') || str_starts_with($ruta, 'https://')) {
            return $ruta;
        }

        return url('storage/' . ltrim($ruta, '/'));
    }
}
