<?php

namespace App\Servicios\Pagos\Openpay;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * La API de la plataforma Openpay, por HTTP y sin SDK. Sirve a las dos marcas
 * que corren sobre ella:
 *
 *  - `openpay`: https://documents.openpay.mx/docs/api/
 *  - `bbva`:    https://docs.ecommercebbva.com/ (Ecommerce BBVA: solo cargos)
 *
 * Se descartaron los SDK oficiales (`openpay/sdk`, `bbva/sdk`): declaran
 * `php >= 5.2.1`, el de BBVA no tiene versiones etiquetadas, y lo que Nódico
 * usa son unas cuantas llamadas que así se prueban con `Http::fake()`.
 *
 * En las dos: rutas `{base}/v1/{MERCHANT_ID}/{recurso}`, HTTP Basic con la
 * llave privada como usuario y contraseña vacía, y `X-Forwarded-For` con la IP
 * del cliente en **toda** llamada («por disposiciones oficiales para la
 * prevención de fraude E-commerce»).
 *
 * La llave privada solo vive aquí: nunca llega al navegador ni a la app.
 */
class ClienteOpenpay
{
    public function __construct(private readonly string $plataforma)
    {
    }

    /** El cliente de la plataforma indicada (`openpay` | `bbva`). */
    public static function para(string $plataforma): self
    {
        return new self($plataforma === 'bbva' ? 'bbva' : 'openpay');
    }

    public function plataforma(): string
    {
        return $this->plataforma;
    }

    public function ajuste(string $clave, mixed $defecto = null): mixed
    {
        return config("pagos.{$this->plataforma}.{$clave}", $defecto);
    }

    /** Lo mínimo para crear un cargo. BBVA exige además la afiliación. */
    public function configurado(): bool
    {
        return filled($this->ajuste('merchant_id'))
            && filled($this->ajuste('llave_privada'))
            && ($this->plataforma !== 'bbva' || filled($this->ajuste('afiliacion')));
    }

    /**
     * Si en esta plataforma se usa la renovación automática: clientes,
     * tarjeta guardada, planes, suscripciones y webhook. Siempre en Openpay;
     * en BBVA, solo con `BBVA_SUSCRIPCIONES` (ver config/pagos.php).
     */
    public function conSuscripciones(): bool
    {
        return (bool) $this->ajuste('suscripciones', false);
    }

    /**
     * Las plataformas configuradas que usan suscripciones: las que hay que
     * sincronizar y de las que se aceptan webhooks. Incluye la que no está
     * activa, porque lo ya suscrito sigue su curso aunque se cambie.
     *
     * @return list<string>
     */
    public static function conSuscripcionesConfiguradas(): array
    {
        return array_values(array_filter(
            ['openpay', 'bbva'],
            fn (string $p) => ($api = self::para($p))->configurado() && $api->conSuscripciones(),
        ));
    }

    public function afiliacion(): ?string
    {
        return $this->ajuste('afiliacion') ?: null;
    }

    public function enSandbox(): bool
    {
        return (bool) $this->ajuste('sandbox', true);
    }

    // ── Cargos ──────────────────────────────────────────────────────────────

    /**
     * POST /charges, o /customers/{id}/charges si el cargo es de un cliente.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>  el objeto transacción
     *
     * @throws ErrorDeOpenpay
     */
    public function crearCargo(array $datos, string $ipCliente, ?string $clienteId = null): array
    {
        return $this->enviar(fn () => $this->peticion($ipCliente)->post($this->deCliente($clienteId).'charges', $datos));
    }

    /**
     * GET /charges/{id} (o el del cliente): la única fuente de verdad sobre si
     * se pagó.
     *
     * @return array<string, mixed>  el objeto transacción
     *
     * @throws ErrorDeOpenpay
     */
    public function obtenerCargo(string $transaccionId, string $ipCliente, ?string $clienteId = null): array
    {
        return $this->enviar(fn () => $this->peticion($ipCliente)
            ->get($this->deCliente($clienteId).'charges/'.rawurlencode($transaccionId)));
    }

    // ── Clientes y tarjetas (solo Openpay) ──────────────────────────────────

    /**
     * POST /customers — https://documents.openpay.mx/docs/api/index.html#crear-un-nuevo-cliente
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     *
     * @throws ErrorDeOpenpay
     */
    public function crearCliente(array $datos, string $ipCliente): array
    {
        return $this->enviar(fn () => $this->peticion($ipCliente)->post('customers', $datos));
    }

    /**
     * GET /customers?external_id= — para recuperar un cliente ya creado (si la
     * creación respondió 2003, «ya existe», tras un corte de red).
     *
     * @return array<string, mixed>|null
     *
     * @throws ErrorDeOpenpay
     */
    public function buscarClientePorExterno(string $externo, string $ipCliente): ?array
    {
        $lista = $this->enviar(fn () => $this->peticion($ipCliente)->get('customers', ['external_id' => $externo]));

        return $lista[0] ?? null;
    }

    /**
     * POST /customers/{id}/cards con el token de openpay.js: la tarjeta queda
     * guardada en Openpay (no en Nódico) y se puede volver a cobrar.
     * https://documents.openpay.mx/docs/api/index.html#crear-una-tarjeta-con-token
     *
     * @return array<string, mixed>  el objeto tarjeta
     *
     * @throws ErrorDeOpenpay
     */
    public function guardarTarjeta(string $clienteId, string $token, string $deviceSessionId, string $ipCliente): array
    {
        return $this->enviar(fn () => $this->peticion($ipCliente)->post($this->deCliente($clienteId).'cards', [
            'token_id'          => $token,
            'device_session_id' => $deviceSessionId,
        ]));
    }

    // ── Planes y suscripciones (solo Openpay) ───────────────────────────────
    //
    // Contrato comprobado contra el sandbox el 29-sep-2026: el plan se crea con
    // importe en pesos; la suscripción acepta `source_id` (la referencia; la
    // guía dice `card_id`), queda en `trial` hasta `trial_end_date` y cobra el
    // día siguiente; `cancel_at_period_end` se puede poner y quitar.

    /**
     * POST /plans — https://documents.openpay.mx/docs/api/index.html#crear-un-nuevo-plan
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     *
     * @throws ErrorDeOpenpay
     */
    public function crearPlan(array $datos): array
    {
        return $this->enviar(fn () => $this->peticion('127.0.0.1')->post('plans', $datos));
    }

    /**
     * POST /customers/{id}/subscriptions
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     *
     * @throws ErrorDeOpenpay
     */
    public function crearSuscripcion(string $clienteId, array $datos, string $ipCliente): array
    {
        return $this->enviar(fn () => $this->peticion($ipCliente)->post($this->deCliente($clienteId).'subscriptions', $datos));
    }

    /**
     * GET /customers/{id}/subscriptions/{id}
     *
     * @return array<string, mixed>
     *
     * @throws ErrorDeOpenpay
     */
    public function obtenerSuscripcion(string $clienteId, string $suscripcionId): array
    {
        return $this->enviar(fn () => $this->peticion('127.0.0.1')
            ->get($this->deCliente($clienteId).'subscriptions/'.rawurlencode($suscripcionId)));
    }

    /**
     * PUT /customers/{id}/subscriptions/{id} (p. ej. `cancel_at_period_end`).
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     *
     * @throws ErrorDeOpenpay
     */
    public function actualizarSuscripcion(string $clienteId, string $suscripcionId, array $datos, string $ipCliente): array
    {
        return $this->enviar(fn () => $this->peticion($ipCliente)
            ->put($this->deCliente($clienteId).'subscriptions/'.rawurlencode($suscripcionId), $datos));
    }

    // ── Apoyo ───────────────────────────────────────────────────────────────

    private function deCliente(?string $clienteId): string
    {
        return $clienteId ? 'customers/'.rawurlencode($clienteId).'/' : '';
    }

    private function peticion(string $ipCliente): PendingRequest
    {
        return Http::baseUrl($this->base().'/v1/'.$this->ajuste('merchant_id').'/')
            ->withBasicAuth((string) $this->ajuste('llave_privada'), '')
            ->withHeaders(['X-Forwarded-For' => $ipCliente])
            ->acceptJson()
            ->asJson()
            ->timeout((int) $this->ajuste('timeout', 30));
    }

    private function base(): string
    {
        return rtrim($this->enSandbox() ? $this->ajuste('url_sandbox') : $this->ajuste('url_produccion'), '/');
    }

    /** @throws ErrorDeOpenpay */
    private function enviar(callable $llamada): array
    {
        try {
            $respuesta = $llamada();
        } catch (ConnectionException $e) {
            throw ErrorDeOpenpay::sinConexion($e->getMessage());
        }

        if ($respuesta->failed()) {
            throw ErrorDeOpenpay::desde($respuesta);
        }

        return $respuesta->json() ?? [];
    }
}
