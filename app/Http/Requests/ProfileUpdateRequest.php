<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // El correo **ya no se cambia aquí** (Fase 4.C): tiene su propio flujo en
        // «Mi seguridad», con verificación de la dirección nueva y aviso a la
        // anterior. Este formulario solo edita el nombre; cualquier `email` que
        // llegue se ignora porque no está entre las reglas.
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
