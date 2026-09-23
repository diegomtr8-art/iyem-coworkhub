<?php

namespace App\Http\Controllers\Portal\Concerns;

use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Membresias\MembresiaDelMiembro;

/**
 * La membresía vigente del miembro y su estado, resueltos igual en todas las
 * pantallas del portal.
 *
 * La regla vive en `MembresiaDelMiembro`, que también usa la API de la app;
 * esto es solo el atajo que ya usaban los controladores.
 */
trait ResuelveLaMembresia
{
    protected function membresiaVigente(User $usuario): ?Suscripcion
    {
        return app(MembresiaDelMiembro::class)->vigente($usuario);
    }

    protected function estadoDeLaMembresia(User $usuario, ?Suscripcion $suscripcion): array
    {
        return app(MembresiaDelMiembro::class)->estado($usuario, $suscripcion);
    }

    protected function diasRestantes(?Suscripcion $suscripcion): int
    {
        return app(MembresiaDelMiembro::class)->diasRestantes($suscripcion);
    }
}
