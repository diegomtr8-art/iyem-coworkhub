<?php

namespace App\Servicios\Membresias;

use App\Enums\EstadoCuenta;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Nodo Match — asignar y quitar al acompañante.
 *
 * Reglas (decisión de Diego, 31/08/2026): el acompañante tiene que ser una
 * cuenta **registrada, con el correo verificado y sin suspender**. No necesita
 * membresía propia —para eso es acompañante—, pero sí una cuenta real, porque
 * comparte el acceso y la bolsa de horas. El Face ID sigue en recepción.
 *
 * Solo el **titular** gestiona al acompañante (22/09/2026): el acompañante ve la
 * membresía, pero no la administra.
 */
class GestorDeAcompanante
{
    public function __construct(private readonly MembresiaDelMiembro $membresias)
    {
    }

    /** @throws ValidationException */
    public function asignar(User $titular, string $correo): User
    {
        $suscripcion = $this->suscripcionDelTitular($titular);

        $correo    = mb_strtolower(trim($correo));
        $companion = User::whereRaw('LOWER(email) = ?', [$correo])->first();

        // La regla del acompañante «con correo activo».
        if (! $companion || ! $companion->hasVerifiedEmail() || $companion->estado === EstadoCuenta::Suspendida) {
            throw ValidationException::withMessages([
                'email' => 'No puede asignarse el Match: no hay una cuenta activa con ese correo. '
                    . 'Pídele a esa persona que se registre en Nódico y verifique su correo primero.',
            ]);
        }

        if ($companion->id === $titular->id) {
            throw ValidationException::withMessages([
                'email' => 'No puedes asignarte a ti mismo como acompañante.',
            ]);
        }

        // Nadie acompaña dos Match a la vez: la bolsa es compartida y el aforo real.
        $yaEsAcompanante = Suscripcion::where('companion_user_id', $companion->id)
            ->where('estatus', 'Activa')
            ->whereDate('fecha_fin', '>=', now()->toDateString())
            ->whereKeyNot($suscripcion->id)
            ->exists();

        if ($yaEsAcompanante) {
            throw ValidationException::withMessages([
                'email' => 'Esa persona ya es acompañante de otra membresía Match activa.',
            ]);
        }

        // Cambiar de acompañante reinicia el Face ID: el nuevo tiene que registrarlo.
        $suscripcion->update([
            'companion_user_id'    => $companion->id,
            'companion_face_id_ok' => false,
        ]);

        return $companion;
    }

    /** @throws ValidationException */
    public function quitar(User $titular): void
    {
        $this->suscripcionDelTitular($titular)
            ->update(['companion_user_id' => null, 'companion_face_id_ok' => false]);
    }

    /** @throws ValidationException */
    private function suscripcionDelTitular(User $usuario): Suscripcion
    {
        $suscripcion = $this->membresias->vigente($usuario);

        if ($suscripcion && ! $this->membresias->esTitular($usuario, $suscripcion)) {
            throw ValidationException::withMessages([
                'general' => 'Solo ' . ($suscripcion->user?->name ?? 'el titular')
                    . ', titular de la membresía, puede cambiar al acompañante.',
            ]);
        }

        if (! $suscripcion || ($suscripcion->plan?->personas ?? 1) <= 1) {
            throw ValidationException::withMessages([
                'general' => 'Tu plan no admite acompañante.',
            ]);
        }

        return $suscripcion;
    }
}
