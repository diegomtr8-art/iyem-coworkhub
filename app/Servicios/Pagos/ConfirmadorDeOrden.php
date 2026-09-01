<?php

namespace App\Servicios\Pagos;

use App\Enums\EstadoPagoOrden;
use App\Models\OrdenPago;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Membresias\GestorDeMembresias;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirma una orden de pago con referencia (Fase 4.2).
 *
 * Es la acción que **activa la membresía**, y por eso nunca es automática:
 * siempre la dispara caja, con evidencia y en una sola transacción. Se apoya en
 * `GestorDeMembresias`, que ya crea la suscripción, abre el ciclo de horas y deja
 * bitácora; aquí solo se decide **qué** operación (alta nueva o encadenar) y se
 * cierra la orden.
 */
class ConfirmadorDeOrden
{
    public function __construct(private readonly GestorDeMembresias $membresias)
    {
    }

    /**
     * @param array{fecha_pago: string, monto_recibido: float, evidencia: string, nota?: ?string, encadenar?: bool} $datos
     */
    public function confirmar(OrdenPago $orden, User $operativo, array $datos): OrdenPago
    {
        return DB::transaction(function () use ($orden, $operativo, $datos) {
            // Solo se confirma una orden viva. Una vencida se reactiva antes
            // (Fase 4.3) y vuelve a `generada`; una confirmada/cancelada, no.
            if ($orden->estado_pago !== EstadoPagoOrden::Generada) {
                throw ValidationException::withMessages([
                    'orden' => 'Esta orden ya no se puede confirmar (estado: ' . $orden->estado_pago->etiqueta() . ').',
                ]);
            }

            $miembro   = $orden->user;
            $plan      = $orden->plan;
            $encadenar = (bool) ($datos['encadenar'] ?? false);

            // Encadenar: la nueva vigencia arranca cuando termina la actual y NO
            // se cierra la anterior. Si no, es un alta que reemplaza la vigente.
            $fechaInicio    = null;
            $cerrarAnterior = true;
            if ($encadenar) {
                $activa = $miembro->suscripciones()
                    ->where('estatus', 'Activa')
                    ->whereDate('fecha_fin', '>=', now()->toDateString())
                    ->latest('fecha_inicio')->first();
                if ($activa) {
                    $fechaInicio    = CarbonImmutable::parse($activa->fecha_fin)->addDay()->toDateString();
                    $cerrarAnterior = false;
                }
            }

            // Quién confirmó, contra qué evidencia y con qué nota queda en la
            // bitácora del alta (actor = operativo).
            $notaCaja = trim((string) ($datos['nota'] ?? ''));
            $notaBitacora = "Pago por referencia {$orden->referencia} confirmado. Evidencia: {$datos['evidencia']}."
                . ($notaCaja !== '' ? " {$notaCaja}" : '');

            $suscripcion = $this->membresias->alta(
                miembro: $miembro,
                plan: $plan,
                operativo: $operativo,
                fechaInicio: $fechaInicio,
                precioPagado: (float) $datos['monto_recibido'],
                nota: $notaBitacora,
                cerrarAnterior: $cerrarAnterior,
            );

            $orden->update([
                'estado_pago'            => EstadoPagoOrden::Confirmada,
                'confirmada_por_user_id' => $operativo->id,
                'confirmada_en'          => now(),
                'fecha_pago'             => $datos['fecha_pago'],
                'monto_recibido'         => $datos['monto_recibido'],
                'evidencia'              => $datos['evidencia'],
                'nota'                   => $notaCaja !== '' ? $notaCaja : $orden->nota,
                'suscripcion_id'         => $suscripcion->id,
            ]);

            return $orden->fresh(['user', 'plan', 'suscripcion']);
        });
    }
}
