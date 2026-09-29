<?php

namespace App\Http\Controllers;

use App\Models\CargoPasarela;
use App\Models\SuscripcionPasarela;
use App\Servicios\Pagos\Openpay\ConfirmadorDeCargo;
use App\Servicios\Pagos\Openpay\ErrorDeOpenpay;
use App\Servicios\Pagos\Openpay\SuscripcionesOpenpay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Migración a Openpay — paso 4: las notificaciones (webhooks) de Openpay.
 *
 * **Openpay no firma sus notificaciones** (no hay secreto compartido como el
 * `whsec_` de Stripe): lo único que ofrece es HTTP Basic con el usuario y la
 * contraseña que se registran con el webhook
 * (https://documents.openpay.mx/docs/webhooks). Por eso aquí hay dos cerrojos:
 *
 *  1. **HTTP Basic obligatorio**, comparado en tiempo constante. Sin usuario y
 *     contraseña configurados, el endpoint rechaza todo: un webhook que se cree
 *     lo que le llegue es una puerta abierta para activar membresías gratis.
 *  2. **Nunca se cree el cuerpo.** Del aviso solo se toma el id de la
 *     transacción o del cliente, y se **consulta a Openpay** con la llave
 *     privada: el cargo con `ConfirmadorDeCargo` (el mismo de la página de
 *     regreso) y las suscripciones con `SuscripcionesOpenpay::sincronizar`.
 *     Un aviso falso con un id inventado no activa nada.
 *
 * Idempotente de sobra: Openpay reintenta hasta recibir 200 y puede mandar el
 * mismo aviso dos veces, pero confirmar un cargo o sincronizar una suscripción
 * procesan cada cosa una sola vez (`EventoPasarela`).
 *
 * Siempre responde 200 a un aviso auténtico, aunque lo ignore o falle la
 * consulta (la red de seguridad es `nodico:confirmar-cargos` y
 * `nodico:sincronizar-suscripciones`), y siempre deja rastro en la bitácora.
 */
class OpenpayWebhookController extends Controller
{
    public function __invoke(Request $request, ConfirmadorDeCargo $confirmador, SuscripcionesOpenpay $suscripciones): JsonResponse
    {
        if (! $this->autentico($request)) {
            Log::warning('Openpay webhook: rechazado por credenciales.', [
                'ip' => $request->server('REMOTE_ADDR'), 'tipo' => $request->input('type'),
            ]);

            return response()->json(['message' => 'No autorizado.'], 401);
        }

        $tipo = (string) $request->input('type', '');
        $transaccion = (array) $request->input('transaction', []);

        Log::info('Openpay webhook: recibido.', [
            'tipo'        => $tipo,
            'transaccion' => $transaccion['id'] ?? null,
            'cliente'     => $transaccion['customer_id'] ?? null,
            'estado'      => $transaccion['status'] ?? null,
        ]);

        try {
            match (true) {
                // Al registrar el webhook: el código hay que pegarlo en el
                // panel de Openpay para verificarlo.
                $tipo === 'verification' => Log::notice('Openpay webhook: código de verificación.', [
                    'codigo' => $request->input('verification_code'),
                ]),
                str_starts_with($tipo, 'charge.') => $this->porCargo($transaccion, $confirmador, $suscripciones),
                $tipo === 'subscription.charge.failed' => $this->porCliente($transaccion['customer_id'] ?? null, $suscripciones),
                str_starts_with($tipo, 'chargeback.') => Log::warning('Openpay webhook: contracargo; revisarlo en caja.', [
                    'tipo' => $tipo, 'transaccion' => $transaccion['id'] ?? null,
                ]),
                default => null,
            };
        } catch (ErrorDeOpenpay $e) {
            // Openpay no respondió a la consulta: lo retoman los procesos
            // programados. Se responde 200 igual para no provocar reintentos
            // en bucle.
            Log::warning('Openpay webhook: la consulta falló; lo retomará el proceso programado.', $e->contexto());
        }

        return response()->json(['recibido' => true]);
    }

    /**
     * Un aviso de cargo. Si es un cargo que creó Nódico, se confirma
     * consultándolo. Si no (el cobro mensual lo crea Openpay), se sincronizan
     * las suscripciones de ese cliente.
     */
    private function porCargo(array $transaccion, ConfirmadorDeCargo $confirmador, SuscripcionesOpenpay $suscripciones): void
    {
        $id = (string) ($transaccion['id'] ?? '');

        $cargo = $id !== '' ? CargoPasarela::where('pasarela', 'openpay')->where('transaccion_id', $id)->first() : null;

        if ($cargo) {
            $confirmador->confirmar($cargo);

            return;
        }

        $this->porCliente($transaccion['customer_id'] ?? null, $suscripciones);
    }

    private function porCliente(?string $clienteId, SuscripcionesOpenpay $suscripciones): void
    {
        if (! $clienteId) {
            return;
        }

        $vivas = SuscripcionPasarela::where('pasarela', 'openpay')
            ->where('cliente_id', $clienteId)
            ->where(fn ($q) => $q->vivas()->orWhere('estado', 'unpaid'))
            ->get();

        if ($vivas->isEmpty()) {
            Log::info('Openpay webhook: sin cargo ni suscripción de Nódico para este aviso; se ignora.', ['cliente' => $clienteId]);
        }

        $vivas->each(fn (SuscripcionPasarela $s) => $suscripciones->sincronizar($s));
    }

    /**
     * HTTP Basic contra `OPENPAY_WEBHOOK_USUARIO` / `OPENPAY_WEBHOOK_CONTRASENA`.
     * Sin los dos configurados, nada es auténtico.
     */
    private function autentico(Request $request): bool
    {
        $usuario = (string) config('pagos.openpay.webhook_usuario');
        $contrasena = (string) config('pagos.openpay.webhook_contrasena');

        if ($usuario === '' || $contrasena === '') {
            return false;
        }

        return hash_equals($usuario, (string) $request->getUser())
            && hash_equals($contrasena, (string) $request->getPassword());
    }
}
