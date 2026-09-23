<?php

namespace App\Servicios\Membresias;

use App\Models\Suscripcion;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * La membresía vigente de una persona y su estado, resueltos igual en el
 * portal web y en la app.
 *
 * Antes vivía en el trait `ResuelveLaMembresia` y solo buscaba membresías
 * propias. Desde el 22/09/2026 el acompañante de un Nodo Match también tiene
 * membresía vigente: la del titular, con la bolsa compartida (ver
 * docs/API-MOVIL.md §6.5). Por eso esto dejó de ser un trait de controlador:
 * la regla es de negocio y la usan la web, la API y el motor de reservas.
 */
class MembresiaDelMiembro
{
    /**
     * La propia, si la hay; si no, la Match vigente de la que es acompañante.
     *
     * La propia gana siempre: quien tiene su plan y además acompaña a alguien
     * reserva contra su bolsa, no contra la del otro.
     */
    public function vigente(User $usuario): ?Suscripcion
    {
        $propia = $usuario->suscripciones()
            ->with('plan', 'companion')
            ->where('estatus', 'Activa')
            ->latest('fecha_inicio')
            ->first();

        if ($propia) {
            return $propia;
        }

        return Suscripcion::query()
            ->with('plan', 'companion', 'user')
            ->where('companion_user_id', $usuario->id)
            ->where('estatus', 'Activa')
            ->whereDate('fecha_fin', '>=', \App\Models\Reserva::hoy()->toDateString())
            ->latest('fecha_inicio')
            ->first();
    }

    public function esTitular(User $usuario, ?Suscripcion $suscripcion): bool
    {
        return $suscripcion !== null && $suscripcion->user_id === $usuario->id;
    }

    /** `titular`, `acompanante` o `null` si no hay membresía. */
    public function rol(User $usuario, ?Suscripcion $suscripcion): ?string
    {
        if (! $suscripcion) {
            return null;
        }

        return $this->esTitular($usuario, $suscripcion) ? 'titular' : 'acompanante';
    }

    /**
     * Estado de la membresía tal y como se enseña arriba de todo.
     *
     * La regla de la Fase 2.1: si está pendiente o suspendida, **hay que decirlo
     * con claridad y con la acción para resolverlo**.
     *
     * @return array{tiene: bool, tono: string, titulo: string, detalle: string, accion: ?string, accion_href: ?string}
     */
    public function estado(User $usuario, ?Suscripcion $suscripcion): array
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

        $hoy       = \App\Models\Reserva::hoy();
        $fin       = CarbonImmutable::parse($suscripcion->fecha_fin->toDateString(), \App\Models\Reserva::zonaDelCalendario());
        $restantes = (int) $hoy->diffInDays($fin, false);
        $titular   = $this->esTitular($usuario, $suscripcion);

        // Renovar es cosa del titular: al acompañante se le dice con quién
        // hablar en vez de ofrecerle un botón que no puede usar.
        $renovar = $titular
            ? [
                'accion'      => 'Renovar',
                // El cobro ocurre dentro de Nódico (Fase 4.A): al checkout
                // interno, no al enlace externo de Stripe.
                'accion_href' => $suscripcion->plan
                    ? route('portal.contratar', ['plan' => $suscripcion->plan->id])
                    : route('membresias'),
            ]
            : ['accion' => null, 'accion_href' => null];

        $deQuien = $titular ? '' : ' Renueva ' . ($suscripcion->user?->name ?? 'el titular') . ', titular de la membresía.';

        if ($restantes < 0) {
            return [
                'tiene'   => true,
                'tono'    => 'problema',
                'titulo'  => 'Tu membresía venció',
                'detalle' => 'Terminó el ' . $fin->translatedFormat('j \d\e F') . '. Renuévala para seguir reservando.' . $deQuien,
                ...$renovar,
            ];
        }

        if ($restantes <= 7) {
            return [
                'tiene'   => true,
                'tono'    => 'atencion',
                'titulo'  => $restantes === 0
                    ? 'Tu membresía termina hoy'
                    : 'Tu membresía termina en ' . $restantes . ' día' . ($restantes === 1 ? '' : 's'),
                'detalle' => 'Vence el ' . $fin->translatedFormat('j \d\e F') . '.' . $deQuien,
                ...$renovar,
            ];
        }

        return [
            'tiene'       => true,
            'tono'        => 'bien',
            'titulo'      => $suscripcion->plan?->nombre ?? 'Membresía activa',
            'detalle'     => 'Activa hasta el ' . $fin->translatedFormat('j \d\e F') . '.'
                . ($titular ? '' : ' Compartida con ' . ($suscripcion->user?->name ?? 'el titular') . '.'),
            'accion'      => null,
            'accion_href' => null,
        ];
    }

    /** Días que faltan para que venza. Nunca negativo. */
    public function diasRestantes(?Suscripcion $suscripcion): int
    {
        if (! $suscripcion) {
            return 0;
        }

        return max(0, (int) \App\Models\Reserva::hoy()->diffInDays(
            CarbonImmutable::parse($suscripcion->fecha_fin->toDateString(), \App\Models\Reserva::zonaDelCalendario()),
            false,
        ));
    }
}
