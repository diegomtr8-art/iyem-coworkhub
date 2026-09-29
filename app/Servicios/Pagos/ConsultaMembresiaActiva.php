<?php

namespace App\Servicios\Pagos;

use App\Models\User;

/** ¿Ya hay membresía vigente? Igual para cualquier pasarela. */
trait ConsultaMembresiaActiva
{
    public function membresiaActiva(User $usuario): bool
    {
        return $usuario->suscripciones()
            ->where('estatus', 'Activa')
            ->where('fecha_fin', '>=', now()->toDateString())
            ->exists();
    }
}
