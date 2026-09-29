<?php

namespace App\Servicios\Pagos;

use App\Models\CargoPasarela;
use App\Models\Plane;
use App\Models\User;
use App\Servicios\Pagos\Bbva\ClienteBbva;
use App\Servicios\Pagos\Bbva\ConfirmadorDeCargo;
use App\Servicios\Pagos\Bbva\ErrorDeBbva;
use App\Servicios\Pagos\Bbva\MensajesDeBbva;
use App\Servicios\Pagos\Contratos\PasarelaDePagos;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cobro con tarjeta vía Ecommerce BBVA, con el formulario del banco (VPOS).
 *
 * El flujo (https://docs.ecommercebbva.com/#cargos-con-vpos):
 *  1. Nódico crea el cargo en el servidor. Nace `charge_pending` y trae
 *     `payment_method.url`.
 *  2. La persona va a esa URL, teclea la tarjeta **en el formulario de BBVA**
 *     (nunca en Nódico) y pasa el 3-D Secure de su banco.
 *  3. BBVA la regresa a `redirect_url?id={transacción}`.
 *  4. Nódico consulta el cargo con la llave privada (`ConfirmadorDeCargo`) y
 *     solo entonces activa.
 *
 * Lo que BBVA **no** tiene documentado y por eso aquí no existe: suscripciones,
 * tarjetas guardadas y webhooks (docs/PAGOS-BBVA.md §2). Un plan «recurrente»
 * se cobra por periodo y la renovación la hace la persona desde el aviso de
 * vencimiento (plan C).
 *
 * Se descartó a propósito el «cargo sin VPOS»: manda número y CVV desde
 * nuestro servidor.
 */
class PasarelaBbva implements PasarelaDePagos
{
    use ConsultaMembresiaActiva;

    public function __construct(
        private readonly ClienteBbva $bbva,
        private readonly ConfirmadorDeCargo $confirmador,
    ) {
    }

    public function nombre(): string
    {
        return 'bbva';
    }

    public function etiqueta(): string
    {
        return 'BBVA';
    }

    public function disponible(): bool
    {
        return $this->bbva->configurado();
    }

    public function disponiblePara(Plane $plan): bool
    {
        return $this->disponible() && (float) $plan->precio > 0;
    }

    /** Sin suscripciones en la API: nada se cobra solo. */
    public function renuevaSola(Plane $plan): bool
    {
        return false;
    }

    /** Con el formulario del banco no hace falta ninguna llave en el navegador. */
    public function llavePublica(): ?string
    {
        return null;
    }

    /**
     * Crea el cargo y devuelve a dónde mandar a la persona.
     *
     * Protección contra cobros duplicados (la versión BBVA de
     * `suscripcionEnCurso()`): si hay un cargo reciente del mismo plan
     * esperando al banco, se retoma ese mismo en vez de crear otro; y si el
     * plan se acaba de pagar, no se deja pagar otra vez en el momento. Todo bajo
     * un candado por persona, para que un doble clic no cree dos cargos.
     *
     * @return array{modo: string, url: string, cargo_id: int}
     *
     * @throws ValidationException
     */
    public function preparar(User $usuario, Plane $plan, bool $exigirConfiguracion = true, string $origen = 'web'): array
    {
        if (! $this->disponiblePara($plan)) {
            throw ValidationException::withMessages([
                'plan' => 'El pago con tarjeta todavía no está disponible. Puedes pagar por referencia.',
            ]);
        }

        $origen = $origen === 'app' ? 'app' : 'web';

        return Cache::lock("bbva:preparar:{$usuario->id}", 20)->block(10, function () use ($usuario, $plan, $origen) {
            $this->rechazarSiRecienPagado($usuario, $plan);

            if ($retomado = $this->retomarPendiente($usuario, $plan, $origen)) {
                return $retomado;
            }

            return $this->crearCargo($usuario, $plan, $origen);
        });
    }

    /** Con BBVA no hay suscripción que crear: el periodo se paga con `preparar`. */
    public function suscribir(User $usuario, Plane $plan, string $metodoDePago): ?string
    {
        throw ValidationException::withMessages([
            'plan' => 'La renovación automática no está disponible con el pago actual. Paga el periodo y te avisamos antes de que venza.',
        ]);
    }

    public function suscripcionEnCurso(User $usuario): string|false
    {
        return false;
    }

    /**
     * Lo que pregunta «confirmando tu pago». Si el cargo sigue en el banco y
     * hace más de unos segundos que no se consulta, se consulta ahora: así la
     * pantalla responde en cuanto BBVA lo sabe, sin esperar al proceso
     * programado, y sin martillar la API cada dos segundos.
     */
    public function estadoDelCobro(User $usuario, ?int $cargoId = null): array
    {
        if (! $cargoId) {
            return ['activa' => $this->membresiaActiva($usuario)];
        }

        $cargo = CargoPasarela::where('user_id', $usuario->id)->whereKey($cargoId)->first();

        if (! $cargo) {
            return ['activa' => false, 'estado' => 'desconocido', 'mensaje' => 'No encontramos ese pago.'];
        }

        if ($cargo->estado === CargoPasarela::PENDIENTE
            && (! $cargo->ultima_consulta_en || $cargo->ultima_consulta_en->lt(now()->subSeconds(3)))) {
            $this->consultarSinRomper($cargo);
        }

        return $this->describir($cargo->refresh());
    }

    /** @return array<string, mixed> */
    public function describir(CargoPasarela $cargo): array
    {
        return [
            'activa'   => $cargo->estado === CargoPasarela::COMPLETADO,
            'estado'   => $cargo->estado,
            'cargo_id' => $cargo->id,
            'plan_id'  => $cargo->plan_id,
            'mensaje'  => match ($cargo->estado) {
                CargoPasarela::COMPLETADO  => '¡Listo! Tu pago quedó confirmado.',
                CargoPasarela::PENDIENTE   => 'Tu pago está pendiente de autorización del banco.',
                CargoPasarela::FALLIDO     => MensajesDeBbva::paraCargoFallido(
                    $cargo->error_mensaje,
                    is_numeric($cargo->error_codigo) ? (int) $cargo->error_codigo : null,
                ),
                CargoPasarela::CANCELADO, CargoPasarela::ABANDONADO
                    => 'Este pago no se terminó. Puedes empezarlo de nuevo.',
                CargoPasarela::EN_REVISION => 'Estamos revisando este pago. Si ya se cobró, te escribimos en breve.',
                CargoPasarela::DEVUELTO    => 'Este pago fue devuelto a tu tarjeta.',
                default                    => 'Estamos preparando tu pago.',
            },
            // Solo mientras siga en el banco: para «Terminar el pago».
            'url_pago' => $cargo->estado === CargoPasarela::PENDIENTE ? $cargo->url_pago : null,
        ];
    }

    /** Sin suscripciones: la tarjeta que se muestra es la del último pago confirmado. */
    public function estadoDeRenovacion(User $usuario): array
    {
        $ultimo = CargoPasarela::where('user_id', $usuario->id)
            ->where('estado', CargoPasarela::COMPLETADO)
            ->whereNotNull('tarjeta_ultimos4')
            ->latest('confirmado_en')
            ->first();

        return [
            'metodo_pago'          => $ultimo ? ['marca' => $ultimo->tarjeta_marca, 'ultimos4' => $ultimo->tarjeta_ultimos4] : null,
            'tiene_recurrente'     => false,
            'renovacion_activa'    => false,
            'en_periodo_de_gracia' => false,
        ];
    }

    public function cancelarRenovacion(User $usuario): bool
    {
        return false;
    }

    public function reactivarRenovacion(User $usuario): bool
    {
        return false;
    }

    // ── Apoyo ───────────────────────────────────────────────────────────────

    /** @throws ValidationException */
    private function rechazarSiRecienPagado(User $usuario, Plane $plan): void
    {
        $reciente = CargoPasarela::where('user_id', $usuario->id)
            ->where('plan_id', $plan->id)
            ->where('estado', CargoPasarela::COMPLETADO)
            ->where('confirmado_en', '>=', now()->subMinutes(10))
            ->exists();

        if ($reciente) {
            throw ValidationException::withMessages([
                'plan' => 'Ya recibimos tu pago de este plan hace un momento. Revisa tu membresía antes de pagar otra vez.',
            ]);
        }
    }

    /** @return array{modo: string, url: string, cargo_id: int}|null */
    private function retomarPendiente(User $usuario, Plane $plan, string $origen): ?array
    {
        $pendiente = CargoPasarela::where('user_id', $usuario->id)
            ->where('plan_id', $plan->id)
            ->where('pasarela', 'bbva')
            ->where('origen', $origen)
            ->esperandoAlBanco()
            ->whereNotNull('url_pago')
            ->where('created_at', '>=', now()->subMinutes((int) config('pagos.bbva.minutos_para_retomar', 30)))
            ->latest('id')
            ->first();

        if (! $pendiente) {
            return null;
        }

        // Quizá ya se pagó y la persona no volvió: se pregunta antes de
        // mandarla otra vez al banco.
        $this->consultarSinRomper($pendiente);

        if ($pendiente->estado === CargoPasarela::COMPLETADO) {
            throw ValidationException::withMessages([
                'plan' => 'Ya recibimos tu pago de este plan. Revisa tu membresía antes de pagar otra vez.',
            ]);
        }

        if ($pendiente->estado !== CargoPasarela::PENDIENTE) {
            return null;
        }

        Log::info('BBVA: se retoma un cargo pendiente en vez de crear otro.', ['cargo' => $pendiente->id]);

        return ['modo' => 'redireccion', 'url' => $pendiente->url_pago, 'cargo_id' => $pendiente->id];
    }

    /**
     * @return array{modo: string, url: string, cargo_id: int}
     *
     * @throws ValidationException
     */
    private function crearCargo(User $usuario, Plane $plan, string $origen): array
    {
        $ip = $this->ipDelCliente();

        // La fila va primero: si la red se corta después de que BBVA cree el
        // cargo, sabemos qué se pidió y por cuánto.
        $cargo = CargoPasarela::create([
            'user_id'    => $usuario->id,
            'plan_id'    => $plan->id,
            'pasarela'   => 'bbva',
            // Único entre TODAS las transacciones del comercio. Aleatorio y no
            // consecutivo: local y staging comparten el mismo sandbox.
            'order_id'   => 'nod-'.Str::lower((string) Str::ulid()),
            'importe'    => Importe::enPesos($plan),
            'moneda'     => 'MXN',
            'estado'     => CargoPasarela::CREANDO,
            'origen'     => $origen,
            'ip_cliente' => $ip,
        ]);

        try {
            $respuesta = $this->bbva->crearCargo([
                'affiliation_bbva' => $this->bbva->afiliacion(),
                'amount'           => $cargo->importe,
                'description'      => Str::limit("Nódico — {$plan->nombre}", 250, ''),
                'currency'         => 'MXN',
                'order_id'         => $cargo->order_id,
                'redirect_url'     => $origen === 'app'
                    ? route('pago.bbva.regreso-app')
                    : route('portal.pago.bbva.regreso'),
                'customer'         => $this->cliente($usuario),
            ], $ip);
        } catch (ErrorDeBbva $e) {
            $cargo->update([
                'estado'        => CargoPasarela::FALLIDO,
                'error_codigo'  => $e->codigo !== null ? (string) $e->codigo : null,
                'error_mensaje' => $e->descripcion,
            ]);
            Log::error('BBVA: no se pudo crear el cargo.', ['cargo' => $cargo->id, ...$e->contexto()]);

            throw ValidationException::withMessages(['plan' => MensajesDeBbva::paraError($e)]);
        }

        $transaccion = $respuesta['id'] ?? null;
        $url = $respuesta['payment_method']['url'] ?? null;

        if (! $transaccion || ! $url) {
            $cargo->update(['estado' => CargoPasarela::FALLIDO, 'error_mensaje' => 'Respuesta sin id o sin URL de pago.']);
            Log::error('BBVA: el cargo se creó sin id o sin URL de pago.', ['cargo' => $cargo->id, 'respuesta' => $respuesta]);

            throw ValidationException::withMessages(['plan' => MensajesDeBbva::paraCodigo(null)]);
        }

        $cargo->update([
            'transaccion_id'  => $transaccion,
            'estado'          => CargoPasarela::PENDIENTE,
            'estado_pasarela' => strtolower((string) ($respuesta['status'] ?? '')),
            'url_pago'        => $url,
        ]);

        Log::info('BBVA: cargo creado.', [
            'cargo' => $cargo->id, 'transaccion' => $transaccion, 'plan' => $plan->id, 'importe' => $cargo->importe,
        ]);

        return ['modo' => 'redireccion', 'url' => $url, 'cargo_id' => $cargo->id];
    }

    /**
     * El objeto `customer` que pide el cargo («no se creará una cuenta al
     * cliente»). Nódico guarda el nombre completo en un solo campo.
     *
     * @return array<string, string>
     */
    private function cliente(User $usuario): array
    {
        $partes = preg_split('/\s+/', trim((string) $usuario->name), 2) ?: [];

        return array_filter([
            'name'         => $partes[0] ?? (string) $usuario->name,
            'last_name'    => $partes[1] ?? '',
            'email'        => (string) $usuario->email,
            'phone_number' => (string) ($usuario->telefono ?? ''),
        ], fn ($v) => $v !== '');
    }

    /**
     * La IP que va en `X-Forwarded-For` para el antifraude de BBVA.
     *
     * **No es `$request->ip()`.** Con `trustProxies(at: '*')` (bootstrap/app.php),
     * Laravel toma la IP de la cabecera `X-Forwarded-For`, y Hostinger deja
     * pasar la que mande el visitante: con `X-Forwarded-For: 1.2.3.4`,
     * `ip()` devuelve `1.2.3.4` (comprobado en prueba.nodico.com.mx el
     * 29-sep-2026). En cambio, `REMOTE_ADDR` sí es la IP real: Hostinger la
     * fija con la de la conexión y el visitante no la puede tocar.
     */
    private function ipDelCliente(): string
    {
        return (string) (request()?->server('REMOTE_ADDR') ?: '127.0.0.1');
    }

    private function consultarSinRomper(CargoPasarela $cargo): void
    {
        try {
            $this->confirmador->confirmar($cargo);
        } catch (ErrorDeBbva $e) {
            Log::warning('BBVA: no se pudo consultar el cargo; se reintentará.', ['cargo' => $cargo->id, ...$e->contexto()]);
        }
    }
}
