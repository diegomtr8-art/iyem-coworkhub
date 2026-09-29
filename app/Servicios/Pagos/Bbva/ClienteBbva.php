<?php

namespace App\Servicios\Pagos\Bbva;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * La API de Ecommerce BBVA, por HTTP y sin SDK.
 *
 * Se descartó el SDK oficial (`bbva/sdk`): no tiene versiones etiquetadas, solo
 * ramas, y declara `php >= 5.2.1`. Lo que Nódico usa son dos llamadas, y así se
 * prueban con `Http::fake()` (docs/PAGOS-BBVA.md §1).
 *
 * Lo que manda la documentación (https://docs.ecommercebbva.com/):
 *  - Rutas `{base}/v1/{MERCHANT_ID}/{recurso}`.
 *  - HTTP Basic: usuario = llave privada, contraseña vacía.
 *  - `X-Forwarded-For` con la IP del cliente en **toda** llamada, «por
 *    disposiciones oficiales para la prevención de fraude E-commerce».
 *
 * La llave privada solo vive aquí: nunca llega al navegador ni a la app.
 */
class ClienteBbva
{
    /** Los tres datos sin los que no se puede crear un cargo. */
    public function configurado(): bool
    {
        return filled(config('pagos.bbva.merchant_id'))
            && filled(config('pagos.bbva.llave_privada'))
            && filled(config('pagos.bbva.afiliacion'));
    }

    public function afiliacion(): string
    {
        return (string) config('pagos.bbva.afiliacion');
    }

    public function enSandbox(): bool
    {
        return (bool) config('pagos.bbva.sandbox', true);
    }

    /**
     * POST /charges — cargo con redirección al formulario del banco (VPOS).
     * https://docs.ecommercebbva.com/#con-vpos
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>  el objeto transacción
     *
     * @throws ErrorDeBbva
     */
    public function crearCargo(array $datos, string $ipCliente): array
    {
        return $this->enviar(fn () => $this->peticion($ipCliente)->post('charges', $datos));
    }

    /**
     * GET /charges/{id} — la única fuente de verdad sobre si se pagó.
     * https://docs.ecommercebbva.com/#obtener-un-cargo
     *
     * @return array<string, mixed>  el objeto transacción
     *
     * @throws ErrorDeBbva
     */
    public function obtenerCargo(string $transaccionId, string $ipCliente): array
    {
        return $this->enviar(fn () => $this->peticion($ipCliente)->get('charges/'.rawurlencode($transaccionId)));
    }

    private function peticion(string $ipCliente): PendingRequest
    {
        return Http::baseUrl($this->base().'/v1/'.config('pagos.bbva.merchant_id').'/')
            ->withBasicAuth((string) config('pagos.bbva.llave_privada'), '')
            ->withHeaders(['X-Forwarded-For' => $ipCliente])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('pagos.bbva.timeout', 30));
    }

    private function base(): string
    {
        return rtrim($this->enSandbox()
            ? config('pagos.bbva.url_sandbox')
            : config('pagos.bbva.url_produccion'), '/');
    }

    /** @throws ErrorDeBbva */
    private function enviar(callable $llamada): array
    {
        try {
            $respuesta = $llamada();
        } catch (ConnectionException $e) {
            throw ErrorDeBbva::sinConexion($e->getMessage());
        }

        if ($respuesta->failed()) {
            throw ErrorDeBbva::desde($respuesta);
        }

        return $respuesta->json() ?? [];
    }
}
