<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\ControlDeIntentos;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $control = app(ControlDeIntentos::class);

        $this->asegurarQueNoHayEspera($control);

        if (Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $control->limpiar($this->correo());

            return;
        }

        $usuario = User::where('email', $this->correo())->first();

        // B — Enumeración por tiempo de respuesta.
        //
        // Cuando el correo no existe, `Auth::attempt` no llega siquiera a
        // comparar hashes: responde en una fracción del tiempo que tarda un
        // fallo de contraseña sobre una cuenta real. Con un cronómetro, eso
        // basta para averiguar quién está registrado en Nódico. Se paga el
        // mismo bcrypt contra un hash señuelo para que los dos caminos cuesten
        // lo mismo.
        if (! $usuario) {
            Hash::check((string) $this->string('password'), $this->hashSenuelo());
        }

        $espera = $control->registrarFallo($this->correo(), (string) $this->ip());

        if ($espera > 0) {
            // El listener de este evento escribe la bitácora y avisa por correo
            // al titular, si la cuenta existe.
            event(new Lockout($this));

            throw ValidationException::withMessages([
                'email' => $this->mensajeDeEspera($espera),
            ]);
        }

        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function asegurarQueNoHayEspera(ControlDeIntentos $control): void
    {
        $espera = $control->esperaPendiente($this->correo(), (string) $this->ip());

        if ($espera === 0) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => $this->mensajeDeEspera($espera),
        ]);
    }

    /**
     * Retraso creciente, no bloqueo seco: el mensaje dice cuánto falta para
     * que quien de verdad tecleó mal no se quede mirando una pared.
     */
    private function mensajeDeEspera(int $segundos): string
    {
        if ($segundos < 60) {
            return "Demasiados intentos seguidos. Vuelve a intentarlo en {$segundos} segundos.";
        }

        $minutos = (int) ceil($segundos / 60);

        return "Demasiados intentos seguidos. Vuelve a intentarlo en {$minutos} minuto"
            . ($minutos === 1 ? '' : 's') . '.';
    }

    /**
     * Hash contra el que comparar cuando la cuenta no existe.
     *
     * Se calcula una sola vez y se guarda: generarlo en cada intento fallido
     * costaría **dos** bcrypt en vez de uno, y volvería el camino del correo
     * inexistente más lento que el real — la misma fuga, del revés.
     */
    private function hashSenuelo(): string
    {
        return Cache::rememberForever(
            'auth:hash-senuelo',
            fn () => Hash::make(Str::random(64)),
        );
    }

    private function correo(): string
    {
        return Str::lower(trim((string) $this->string('email')));
    }
}
