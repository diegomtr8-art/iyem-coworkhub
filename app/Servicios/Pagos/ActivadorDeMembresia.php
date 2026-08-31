<?php

namespace App\Servicios\Pagos;

use App\Enums\EstadoCuenta;
use App\Models\Plane;
use App\Models\Suscripcion;
use App\Models\User;
use App\Notifications\PagoRechazado;
use App\Servicios\Membresias\GestorDeMembresias;
use Illuminate\Support\Facades\DB;

/**
 * Fase 4.A — traduce lo que dice Stripe en membresías de Nódico.
 *
 * Lo llama **el webhook**, nunca la página de retorno: la verdad de un pago la
 * dice Stripe, no el navegador (que puede cerrarse antes de volver). Y sin
 * operativo humano: estas altas las hace el sistema, así que la bitácora las
 * registra con actor nulo (por eso `GestorDeMembresias` acepta operativo null).
 *
 * Se apoya en `GestorDeMembresias` para no duplicar la apertura de ciclo ni la
 * bitácora: aquí solo se decide **qué** operación toca (alta, cambio de plan o
 * renovación), no cómo se hace.
 */
class ActivadorDeMembresia
{
    public function __construct(private readonly GestorDeMembresias $membresias)
    {
    }

    /**
     * Primera compra o cambio de plan. Idempotente por su cuenta: si el miembro
     * ya tiene el mismo plan activo, no hace nada (además de la idempotencia por
     * event id que envuelve la llamada).
     */
    public function activar(User $miembro, Plane $plan, ?float $precioPagado = null): ?Suscripcion
    {
        return DB::transaction(function () use ($miembro, $plan, $precioPagado) {
            // Solo cuenta una membresía **vigente**. Una con estatus «Activa» pero
            // ya vencida por fecha (la persona no renovó a tiempo) no debe hacer
            // que un pago nuevo del mismo plan se tome por un reintento y no
            // renueve nada: eso dejaba «confirmando el pago» colgado para siempre.
            // Si no hay vigente, se hace alta() —que además cierra la anterior—.
            $activa = $miembro->suscripciones()
                ->where('estatus', 'Activa')
                ->whereDate('fecha_fin', '>=', now()->toDateString())
                ->latest('fecha_inicio')
                ->first();

            // Ya tiene justo este plan vigente: el evento es un reintento o un
            // cruce; no se abre otro ciclo.
            if ($activa && $activa->plan_id === $plan->id) {
                return $activa;
            }

            // Tiene otro plan activo: es un cambio de plan → ciclo nuevo desde
            // cero (decisión de Nódico). Si no tiene ninguno, es un alta.
            if ($activa) {
                return $this->membresias->cambiarDePlan(
                    $activa,
                    $plan,
                    null,
                    'Cambio de plan pagado en línea.',
                );
            }

            return $this->membresias->alta(
                $miembro,
                $plan,
                null,
                precioPagado: $precioPagado ?? (float) $plan->precio,
                nota: 'Alta pagada en línea.',
            );
        });
    }

    /**
     * Renovación recurrente: llega cuando Stripe cobra un ciclo nuevo de una
     * suscripción que ya existía. Extiende la vigencia y abre el ciclo siguiente.
     */
    public function renovar(User $miembro, Plane $plan, ?float $precioPagado = null): ?Suscripcion
    {
        $activa = $miembro->suscripciones()
            ->where('estatus', 'Activa')
            ->latest('fecha_inicio')
            ->first();

        if (! $activa) {
            // No había membresía viva: trátalo como un alta.
            return $this->activar($miembro, $plan, $precioPagado);
        }

        return $this->membresias->renovar($activa, null, $precioPagado);
    }

    /** Un cobro recurrente falló: se suspende la membresía y se avisa. */
    public function suspenderPorImpago(User $miembro, string $motivo = 'Cobro rechazado por el banco.'): void
    {
        $activa = $miembro->suscripciones()
            ->where('estatus', 'Activa')
            ->latest('fecha_inicio')
            ->first();

        if ($activa) {
            $this->membresias->suspender($activa, null, $motivo);
        }

        // La cuenta queda suspendida aunque no hubiera suscripción activa que
        // suspender: el impago es motivo suficiente.
        if ($miembro->estado !== EstadoCuenta::Suspendida) {
            $miembro->cambiarEstado(EstadoCuenta::Suspendida);
        }

        $miembro->notify(new PagoRechazado($motivo));
    }

    /**
     * La suscripción recurrente se canceló en Stripe (o llegó a su fin): se deja
     * de renovar. La membresía vigente **no** se corta a mitad de periodo —el
     * miembro pagó por él—; simplemente ya no se auto-renueva.
     */
    public function detenerRenovacion(User $miembro): void
    {
        $miembro->suscripciones()
            ->where('estatus', 'Activa')
            ->update(['auto_renovar' => false]);
    }
}
