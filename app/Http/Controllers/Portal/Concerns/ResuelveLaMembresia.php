<?php

namespace App\Http\Controllers\Portal\Concerns;

use App\Models\Suscripcion;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * La membresía vigente del miembro y su estado, resueltos igual en las ocho
 * pantallas del portal.
 *
 * Cada controlador tenía su propia consulta con su propio `latest()`, y bastaba
 * que una ordenara distinto para que dos pantallas enseñaran membresías
 * distintas a la misma persona.
 */
trait ResuelveLaMembresia
{
    protected function membresiaVigente(User $usuario): ?Suscripcion
    {
        return $usuario->suscripciones()
            ->with('plan', 'companion')
            ->where('estatus', 'Activa')
            ->latest('fecha_inicio')
            ->first();
    }

    /**
     * Estado de la membresía tal y como se enseña arriba de todo en el portal.
     *
     * La regla de la Fase 2.1: si está pendiente o suspendida, **hay que decirlo
     * con claridad y con la acción para resolverlo**. Un portal que se limita a
     * no dejar reservar, sin explicar por qué, manda a la persona a recepción a
     * preguntar.
     */
    protected function estadoDeLaMembresia(User $usuario, ?Suscripcion $suscripcion): array
    {
        if (! $suscripcion) {
            $ultima = $usuario->suscripciones()->with('plan')->latest('fecha_fin')->first();

            return [
                'tiene'      => false,
                'tono'       => 'problema',
                'titulo'     => $ultima ? 'Tu membresía terminó' : 'Todavía no tienes membresía',
                'detalle'    => $ultima
                    ? 'Tu ' . $ultima->plan?->nombre . ' venció el '
                        . CarbonImmutable::parse($ultima->fecha_fin)->translatedFormat('j \d\e F') . '.'
                    : 'Elige un plan y empieza a usar Nódico hoy mismo.',
                'accion'      => 'Ver los planes',
                'accion_href' => route('membresias'),
            ];
        }

        $hoy       = CarbonImmutable::today();
        $fin       = CarbonImmutable::parse($suscripcion->fecha_fin);
        $restantes = (int) $hoy->diffInDays($fin, false);

        if ($restantes < 0) {
            return [
                'tiene'       => true,
                'tono'        => 'problema',
                'titulo'      => 'Tu membresía venció',
                'detalle'     => 'Terminó el ' . $fin->translatedFormat('j \d\e F') . '. Renuévala para seguir reservando.',
                'accion'      => 'Renovar',
                'accion_href' => $suscripcion->plan?->stripe_url ?: route('membresias'),
            ];
        }

        if ($restantes <= 7) {
            return [
                'tiene'       => true,
                'tono'        => 'atencion',
                'titulo'      => $restantes === 0
                    ? 'Tu membresía termina hoy'
                    : 'Tu membresía termina en ' . $restantes . ' día' . ($restantes === 1 ? '' : 's'),
                'detalle'     => 'Vence el ' . $fin->translatedFormat('j \d\e F') . '.',
                'accion'      => 'Renovar',
                'accion_href' => $suscripcion->plan?->stripe_url ?: route('membresias'),
            ];
        }

        return [
            'tiene'       => true,
            'tono'        => 'bien',
            'titulo'      => $suscripcion->plan?->nombre ?? 'Membresía activa',
            'detalle'     => 'Activa hasta el ' . $fin->translatedFormat('j \d\e F') . '.',
            'accion'      => null,
            'accion_href' => null,
        ];
    }

    /** Días que faltan para que venza. Nunca negativo. */
    protected function diasRestantes(?Suscripcion $suscripcion): int
    {
        if (! $suscripcion) {
            return 0;
        }

        return max(0, (int) CarbonImmutable::today()->diffInDays(
            CarbonImmutable::parse($suscripcion->fecha_fin),
            false,
        ));
    }
}
