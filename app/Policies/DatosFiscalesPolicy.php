<?php

namespace App\Policies;

use App\Models\DatosFiscales;
use App\Models\User;

/**
 * Quién puede ver y tocar los datos fiscales de alguien (Fase 1.3).
 *
 * El criterio es estricto a propósito: **el dueño y administración, nadie más.**
 * Recepción (`staff`) opera el día a día y no necesita el RFC de nadie para
 * registrar una entrada o mover una reserva. Y los permisos existentes ya
 * dicen esto mismo: `gestionar-facturacion` es de administración.
 *
 * Un miembro no puede ver los datos de otro miembro bajo ninguna circunstancia.
 */
class DatosFiscalesPolicy
{
    public function view(User $usuario, DatosFiscales $datos): bool
    {
        return $usuario->id === $datos->user_id || $usuario->esAdmin();
    }

    public function update(User $usuario, DatosFiscales $datos): bool
    {
        return $usuario->id === $datos->user_id || $usuario->esAdmin();
    }

    public function delete(User $usuario, DatosFiscales $datos): bool
    {
        return $usuario->id === $datos->user_id || $usuario->esAdmin();
    }

    /** Ver los de un miembro concreto desde la ficha del panel. */
    public function verDeMiembro(User $usuario, User $miembro): bool
    {
        return $usuario->id === $miembro->id || $usuario->esAdmin();
    }

    /** La exportación para contabilidad saca RFC de mucha gente de golpe. */
    public function exportar(User $usuario): bool
    {
        return $usuario->esAdmin();
    }
}
