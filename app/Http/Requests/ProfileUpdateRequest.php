<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],

            /*
             * B — Cambiar el correo pide la contraseña actual.
             *
             * El correo es la llave de recuperación de la cuenta: quien lo
             * cambia puede después pedir un restablecimiento y quedarse con
             * todo. Es la acción más delicada del perfil, y con una sesión
             * olvidada en un equipo prestado bastaría un clic.
             *
             * Se pide solo cuando el correo cambia de verdad: exigirla también
             * para corregir una tilde del nombre sería ruido, y el ruido acaba
             * en gente que teclea su contraseña sin leer.
             */
            'current_password' => [
                Rule::requiredIf(fn () => $this->correoCambia()),
                'current_password',
            ],
        ];
    }

    private function correoCambia(): bool
    {
        $nuevo = Str::lower(trim((string) $this->input('email')));

        return $nuevo !== '' && $nuevo !== Str::lower((string) $this->user()->email);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Para cambiar tu correo necesitamos tu contraseña actual.',
        ];
    }
}
