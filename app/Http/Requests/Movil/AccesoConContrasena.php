<?php

namespace App\Http\Requests\Movil;

use App\Http\Requests\Auth\LoginRequest;

/**
 * El acceso con contraseña de la app: las reglas del login web más el teléfono
 * desde el que se entra, que da nombre al token en «Mi seguridad».
 */
class AccesoConContrasena extends LoginRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            ...self::reglasDelDispositivo(),
        ];
    }

    /** @return array<string, array<int, string>> */
    public static function reglasDelDispositivo(): array
    {
        return [
            'dispositivo'    => ['required', 'string', 'max:120'],
            'dispositivo_id' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'plataforma'     => ['nullable', 'in:ios,android'],
        ];
    }
}
