<?php

namespace App\Servicios\Pagos\Openpay;

use App\Models\CargoPasarela;
use App\Models\EventoPasarela;
use App\Servicios\Pagos\ActivadorDeMembresia;
use App\Servicios\Pagos\Importe;
use Illuminate\Support\Facades\Log;

/**
 * **La única puerta** por la que un pago con tarjeta de Openpay (o de
 * Ecommerce BBVA) activa una membresía.
 *
 * La verdad la da la API, consultada desde el servidor con la llave privada
 * (`GET /charges/{id}`, o el del cliente), tal como piden las dos
 * documentaciones: el comercio decide «en base a la información obtenida» de
 * esa consulta (https://docs.ecommercebbva.com/#cargos-con-vpos,
 * https://documents.openpay.mx/docs/three-d-secure). Ni la URL de regreso, ni el
 * navegador, ni la persona deciden nada: el id que traen es una pista. Los
 * webhooks de Openpay, cuando lleguen, tampoco: avisan, y se consulta.
 *
 * Lo llaman la página de regreso, la pantalla «confirmando», la app y el
 * proceso programado `nodico:confirmar-cargos`. Todos pueden consultar el mismo
 * cargo a la vez o varias veces: la activación va envuelta en
 * `EventoPasarela::procesarUnaVez`, así que ocurre una sola vez.
 */
class ConfirmadorDeCargo
{
    public function __construct(
        private readonly ActivadorDeMembresia $activador,
        private readonly SuscripcionesOpenpay $suscripciones,
    ) {
    }

    /**
     * Consulta el cargo, con las llaves de SU plataforma, y actúa según lo que
     * diga la pasarela.
     *
     * @throws ErrorDeOpenpay si la API no responde; el cargo queda como estaba y
     *                     se vuelve a consultar después.
     */
    public function confirmar(CargoPasarela $cargo): CargoPasarela
    {
        if ($cargo->esFinal() || ! $cargo->transaccion_id) {
            return $cargo;
        }

        $datos = ClienteOpenpay::para($cargo->pasarela)
            ->obtenerCargo($cargo->transaccion_id, $cargo->ip_cliente ?: '127.0.0.1', $cargo->cliente_pasarela_id);
        $cargo->ultima_consulta_en = now();

        // Lo que responde la pasarela tiene que ser exactamente lo que se le pidió
        // cobrar. Si no, no se activa nada y se deja para revisión humana.
        if ($discrepancia = $this->discrepancia($cargo, $datos)) {
            $cargo->fill(['estado' => CargoPasarela::EN_REVISION, 'error_mensaje' => $discrepancia])->save();
            Log::error('Pasarela: el cargo consultado no coincide con el que se creó. No se activa nada.', [
                'cargo' => $cargo->id, 'transaccion' => $cargo->transaccion_id, 'motivo' => $discrepancia,
            ]);

            return $cargo;
        }

        $estado = strtolower((string) ($datos['status'] ?? ''));
        $cargo->estado_pasarela = $estado;
        $this->guardarTarjeta($cargo, $datos);

        match ($estado) {
            'completed'  => $this->completar($cargo),
            'failed'     => $cargo->fill([
                'estado'        => CargoPasarela::FALLIDO,
                'error_codigo'  => isset($datos['error_code']) ? (string) $datos['error_code'] : null,
                'error_mensaje' => $datos['error_message'] ?? null,
            ]),
            'cancelled'  => $cargo->estado = CargoPasarela::CANCELADO,
            'refunded'   => $this->devuelto($cargo),
            // Sigue en el banco (la persona no ha terminado el 3-D Secure) o en
            // proceso: no es un pago. Se vuelve a consultar después.
            'charge_pending', 'in_progress' => null,
            default      => Log::warning('Pasarela: estado de cargo desconocido.', ['cargo' => $cargo->id, 'estado' => $estado]),
        };

        $cargo->save();

        // Primer periodo de un plan que se renueva: confirmado el cobro, se da
        // de alta la suscripción en Openpay. Si Openpay no responde ahora, lo
        // reintenta `nodico:sincronizar-suscripciones`; el cobro ya quedó.
        if ($cargo->suscribir && $cargo->estado === CargoPasarela::COMPLETADO) {
            try {
                $this->suscripciones->asegurarAlta($cargo);
            } catch (ErrorDeOpenpay $e) {
                Log::warning('Openpay: el alta de la suscripción se reintentará.', ['cargo' => $cargo->id, ...$e->contexto()]);
            }
        }

        Log::info('Pasarela: cargo consultado.', [
            'cargo'       => $cargo->id,
            'transaccion' => $cargo->transaccion_id,
            'pasarela' => $cargo->pasarela, 'estado_pasarela' => $estado,
            'estado'      => $cargo->estado,
        ]);

        return $cargo;
    }

    private function completar(CargoPasarela $cargo): void
    {
        $miembro = $cargo->usuario;
        $plan = $cargo->plan;

        // La persona borró su cuenta con el pago a medias: el cobro se registra
        // como hecho, pero no hay a quién activarle nada. Caja decide.
        if (! $miembro || ! $plan) {
            $cargo->estado = CargoPasarela::COMPLETADO;
            $cargo->confirmado_en ??= now();
            Log::warning('Pasarela: cargo pagado sin persona o sin plan; no se activa nada.', ['cargo' => $cargo->id]);

            return;
        }

        EventoPasarela::procesarUnaVez($cargo->pasarela, "{$cargo->transaccion_id}:completed", 'charge.completed', function () use ($cargo, $miembro, $plan) {
            // Mismo plan todavía vigente → pagó el periodo siguiente: se
            // extiende (renovar). Si no, alta o cambio de plan (activar, que ya
            // distingue entre los dos). Se decide al confirmar, no al crear el
            // cargo: entre una cosa y otra pueden pasar horas.
            $vigenteMismoPlan = $miembro->suscripciones()
                ->where('estatus', 'Activa')
                ->where('plan_id', $plan->id)
                ->whereDate('fecha_fin', '>=', now()->toDateString())
                ->exists();

            if ($vigenteMismoPlan) {
                $this->activador->renovar($miembro, $plan, $cargo->importe);
                $cargo->operacion = 'renovacion';
            } else {
                $this->activador->activar($miembro, $plan, $cargo->importe);
                $cargo->operacion = 'alta';
            }
        });

        $cargo->estado = CargoPasarela::COMPLETADO;
        $cargo->confirmado_en ??= now();
    }

    /**
     * Una devolución la hace una persona desde el panel del banco; aquí no se
     * revierte la membresía sola. Se registra fuerte para que caja decida.
     */
    private function devuelto(CargoPasarela $cargo): void
    {
        $cargo->estado = CargoPasarela::DEVUELTO;
        Log::warning('Pasarela: cargo devuelto. La membresía no se toca sola; revisarla en caja.', [
            'cargo' => $cargo->id, 'usuario' => $cargo->user_id, 'transaccion' => $cargo->transaccion_id,
        ]);
    }

    private function discrepancia(CargoPasarela $cargo, array $datos): ?string
    {
        if (($datos['id'] ?? null) !== $cargo->transaccion_id) {
            return 'El id de la transacción no coincide.';
        }
        if (($datos['order_id'] ?? null) !== $cargo->order_id) {
            return 'El order_id no coincide.';
        }
        if (! isset($datos['amount']) || ! Importe::iguales((float) $datos['amount'], $cargo->importe)) {
            return 'El importe no coincide: la pasarela dice '.($datos['amount'] ?? 'nada').", se pidió {$cargo->importe}.";
        }
        if (strtoupper((string) ($datos['currency'] ?? 'MXN')) !== strtoupper($cargo->moneda)) {
            return 'La moneda no coincide.';
        }

        return null;
    }

    private function guardarTarjeta(CargoPasarela $cargo, array $datos): void
    {
        $tarjeta = $datos['card'] ?? null;

        if (! is_array($tarjeta)) {
            return;
        }

        $cargo->tarjeta_marca = $tarjeta['brand'] ?? $cargo->tarjeta_marca;

        if (! empty($tarjeta['card_number'])) {
            $cargo->tarjeta_ultimos4 = substr((string) $tarjeta['card_number'], -4);
        }
    }
}
