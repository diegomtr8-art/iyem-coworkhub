<?php

namespace App\Servicios\Pagos;

use App\Models\CargoPasarela;
use App\Models\ClientePasarela;
use App\Models\Plane;
use App\Models\User;
use App\Servicios\Pagos\Contratos\PasarelaDePagos;
use App\Servicios\Pagos\Openpay\ClienteOpenpay;
use App\Servicios\Pagos\Openpay\ConfirmadorDeCargo;
use App\Servicios\Pagos\Openpay\ErrorDeOpenpay;
use App\Servicios\Pagos\Openpay\MensajesDeOpenpay;
use App\Servicios\Pagos\Openpay\SuscripcionesOpenpay;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cobro con tarjeta en la plataforma Openpay, con sus dos marcas:
 *
 *  - **`openpay`** (https://documents.openpay.mx/docs/api/): la tarjeta se
 *    teclea en Nódico y openpay.js la cambia por un token en el navegador; el
 *    número nunca llega a este servidor. Cada miembro tiene un cliente en
 *    Openpay, y en los planes que se renuevan su tarjeta queda guardada **en
 *    Openpay** para los cobros de cada periodo.
 *  - **`bbva`** (https://docs.ecommercebbva.com/): la misma plataforma con
 *    una API recortada (solo cargos). Formulario del banco (VPOS) o token si
 *    el ejecutivo lo autoriza. Ver docs/PAGOS-BBVA.md.
 *
 * En las dos, **la pasarela no activa nada aquí**: la membresía la activa
 * `ConfirmadorDeCargo` cuando la API confirma el cargo al consultarlo.
 *
 * 3-D Secure en Openpay (`pagos.openpay.tres_d_secure`): `si_hace_falta`
 * cobra sin autenticación y, si el antifraude rechaza por riesgo (3005),
 * reintenta con 3-D Secure y la misma tarjeta, como indica
 * https://documents.openpay.mx/docs/three-d-secure. Así, la mayoría paga sin
 * salir de Nódico, y quien lo necesita pasa por la página de su banco.
 */
class PasarelaOpenpay implements PasarelaDePagos
{
    use ConsultaMembresiaActiva;

    private readonly ClienteOpenpay $api;

    public function __construct(
        private readonly string $plataforma,
        private readonly ConfirmadorDeCargo $confirmador,
        private readonly SuscripcionesOpenpay $suscripciones,
    ) {
        $this->api = ClienteOpenpay::para($plataforma);
    }

    public function nombre(): string
    {
        return $this->api->plataforma();
    }

    public function etiqueta(): string
    {
        return $this->esBbva() ? 'BBVA' : 'Openpay';
    }

    public function disponible(): bool
    {
        return $this->api->configurado();
    }

    public function disponiblePara(Plane $plan): bool
    {
        return $this->disponible() && (float) $plan->precio > 0;
    }

    /**
     * La pasarela cobra sola cada mes los planes recurrentes cuyo plan está
     * sincronizado a su precio (`nodico:sincronizar-planes-pasarela`). Sin
     * suscripciones (BBVA mientras no se encienda `BBVA_SUSCRIPCIONES`), se
     * paga por periodo.
     */
    public function renuevaSola(Plane $plan): bool
    {
        return $this->api->conSuscripciones()
            && (bool) $plan->cobro_recurrente
            && $this->suscripciones->planDe($plan, $this->nombre()) !== null;
    }

    /** Solo con la captura en Nódico: la pública únicamente sirve para crear tokens. */
    public function llavePublica(): ?string
    {
        return $this->capturaEnNodico() ? (string) $this->api->ajuste('llave_publica') : null;
    }

    /**
     * La tarjeta se teclea en la página de Nódico (openpay.js → token). Sin
     * llave pública no se puede: se usa el formulario de la pasarela.
     */
    public function capturaEnNodico(): bool
    {
        return $this->api->ajuste('captura') === 'token' && filled($this->api->ajuste('llave_publica'));
    }

    /**
     * Lo que openpay.js necesita en el navegador. Nada secreto: el id de
     * comercio y la llave pública son, por diseño, visibles.
     *
     * @return array{merchant_id: string, llave_publica: string, sandbox: bool}|null
     */
    public function datosParaElNavegador(): ?array
    {
        if (! $this->capturaEnNodico()) {
            return null;
        }

        return [
            'merchant_id'   => (string) $this->api->ajuste('merchant_id'),
            'llave_publica' => (string) $this->api->ajuste('llave_publica'),
            'sandbox'       => $this->api->enSandbox(),
        ];
    }

    /**
     * En los planes que se renuevan, la tarjeta queda guardada en Openpay: es
     * con la que Openpay cobra cada periodo.
     */
    public function guardaTarjeta(Plane $plan): bool
    {
        return $this->api->conSuscripciones() && (bool) $plan->cobro_recurrente;
    }

    /**
     * Cobro con la tarjeta tecleada en Nódico: openpay.js ya la cambió por un
     * token en el navegador. Aquí llegan solo el token y el
     * `device_session_id` del antifraude.
     *
     * Openpay: el cargo es del cliente de la persona. En planes que se renuevan
     * primero se guarda la tarjeta en Openpay y se cobra con ella (queda lista
     * para el periodo siguiente); en pagos únicos se cobra con el token.
     *
     * Ecommerce BBVA: cargo de comercio con el token («cargo sin VPOS»; su
     * documentación no lo describe y exige autorización del ejecutivo).
     *
     * @return array{modo: string, cargo_id: int, url?: string}
     *
     * @throws ValidationException
     */
    public function cobrarConToken(User $usuario, Plane $plan, string $token, string $deviceSessionId): array
    {
        if (! $this->disponiblePara($plan) || ! $this->capturaEnNodico()) {
            throw ValidationException::withMessages([
                'plan' => 'El pago con tarjeta todavía no está disponible. Puedes pagar por referencia.',
            ]);
        }

        return $this->conCandado($usuario, function () use ($usuario, $plan, $token, $deviceSessionId) {
            $this->rechazarSiRecienPagado($usuario, $plan);

            // Nunca dos suscripciones que se cobren solas a la vez: antes de
            // guardar la tarjeta o cobrar nada.
            $renueva = $this->renuevaSola($plan);
            if ($renueva) {
                $this->suscripcionEnCurso($usuario);
            }

            // Un cargo del mismo plan esperando el 3-D Secure: se termina ese,
            // no se cobra otro con la tarjeta nueva.
            if ($retomado = $this->retomarPendiente($usuario, $plan, 'web')) {
                return $retomado;
            }

            // Sin suscripciones: cargo de comercio, sin cliente ni tarjeta
            // guardada (el «cargo sin VPOS» de BBVA).
            if (! $this->api->conSuscripciones()) {
                return $this->crearCargo($usuario, $plan, 'web', [
                    'method' => 'card', 'source_id' => $token, 'device_session_id' => $deviceSessionId,
                ]);
            }

            $cliente = $this->clienteDe($usuario);
            $fuente = $token;

            if ($this->guardaTarjeta($plan)) {
                $fuente = $this->guardarTarjeta($cliente, $token, $deviceSessionId) ?? $token;

                // 2002: esa tarjeta ya estaba guardada. Para la suscripción
                // hace falta su id; si Nódico no lo tiene, no se puede seguir.
                if ($renueva && $fuente === $token && ! $cliente->refresh()->tieneTarjeta()) {
                    throw ValidationException::withMessages([
                        'plan' => 'No pudimos preparar esta tarjeta para la renovación automática. Usa otra tarjeta o escríbenos.',
                    ]);
                }
            }

            return $this->crearCargo($usuario, $plan, 'web', [
                'method' => 'card', 'source_id' => $fuente, 'device_session_id' => $deviceSessionId,
            ], $cliente->cliente_id, suscribir: $renueva);
        });
    }

    /**
     * Cobro con el formulario de la pasarela (redirección): el de BBVA (VPOS)
     * o el de Openpay («cargo con redireccionamiento»). Lo usa la app mientras
     * no tenga la captura en Nódico, y BBVA cuando no hay token autorizado.
     *
     * Protección contra cobros duplicados: si hay un cargo reciente del mismo
     * plan esperando al banco, se retoma ese mismo; si el plan se acaba de
     * pagar, no se deja pagar otra vez en el momento. Todo bajo un candado por
     * persona, para que un doble clic no cree dos cargos.
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

        return $this->conCandado($usuario, function () use ($usuario, $plan, $origen) {
            $this->rechazarSiRecienPagado($usuario, $plan);

            if ($retomado = $this->retomarPendiente($usuario, $plan, $origen)) {
                return $retomado;
            }

            return $this->crearCargo($usuario, $plan, $origen);
        });
    }

    /**
     * Con Openpay la suscripción no se crea desde la pantalla: la da de alta el
     * servidor al confirmarse el primer cobro (`cobrarConToken`). Esto es la
     * hoja de pago de Stripe.
     */
    public function suscribir(User $usuario, Plane $plan, string $metodoDePago): ?string
    {
        throw ValidationException::withMessages([
            'plan' => 'Paga el primer periodo con tu tarjeta; la renovación automática se activa sola al confirmarse.',
        ]);
    }

    /**
     * Protección contra suscripciones duplicadas (la de `PasarelaStripe`):
     * con una suscripción que se cobra sola, no se crea otra. Una ya
     * cancelada al final del periodo no cuenta: no volverá a cobrar.
     *
     * @throws ValidationException
     */
    public function suscripcionEnCurso(User $usuario): string|false
    {
        $viva = $this->suscripciones->vivaDe($usuario, $this->nombre());

        if ($viva && ! $viva->cancelar_al_final) {
            throw ValidationException::withMessages([
                'plan' => 'Ya tienes una membresía con renovación automática: se cobra sola cada periodo. '
                    . 'Para cambiar de plan, escríbenos o cancela la renovación primero.',
            ]);
        }

        return false;
    }

    /**
     * Lo que pregunta «confirmando tu pago». Si el cargo sigue en el banco y
     * hace más de unos segundos que no se consulta, se consulta ahora: así la
     * pantalla responde en cuanto la pasarela lo sabe, sin esperar al proceso
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
                CargoPasarela::FALLIDO     => MensajesDeOpenpay::paraCargoFallido(
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

    /**
     * La tarjeta guardada en Openpay (si no, la del último pago confirmado) y
     * cómo va la suscripción. Solo datos locales: no se llama a Openpay en
     * cada carga del portal.
     */
    public function estadoDeRenovacion(User $usuario): array
    {
        $viva = ! $this->api->conSuscripciones() ? null : $this->suscripciones->vivaDe($usuario, $this->nombre());

        $guardada = ClientePasarela::where('user_id', $usuario->id)
            ->where('pasarela', $this->nombre())
            ->whereNotNull('tarjeta_id')
            ->first();

        $ultimo = $guardada ? null : CargoPasarela::where('user_id', $usuario->id)
            ->where('estado', CargoPasarela::COMPLETADO)
            ->whereNotNull('tarjeta_ultimos4')
            ->latest('confirmado_en')
            ->first();

        $metodo = match (true) {
            (bool) $guardada => ['marca' => $guardada->tarjeta_marca, 'ultimos4' => $guardada->tarjeta_ultimos4],
            (bool) $ultimo   => ['marca' => $ultimo->tarjeta_marca, 'ultimos4' => $ultimo->tarjeta_ultimos4],
            default          => null,
        };

        return [
            'metodo_pago'          => $metodo,
            'tiene_recurrente'     => (bool) $viva,
            'renovacion_activa'    => $viva && ! $viva->cancelar_al_final,
            'en_periodo_de_gracia' => $viva && $viva->cancelar_al_final,
        ];
    }

    /**
     * Deja de cobrar al final del periodo pagado; la membresía sigue hasta su
     * fecha.
     *
     * @throws ValidationException si Openpay no responde
     */
    public function cancelarRenovacion(User $usuario): bool
    {
        $viva = ! $this->api->conSuscripciones() ? null : $this->suscripciones->vivaDe($usuario, $this->nombre());

        if (! $viva || $viva->cancelar_al_final) {
            return false;
        }

        $this->contraOpenpay(fn () => $this->suscripciones->cancelarAlFinal($viva));

        return true;
    }

    /** @throws ValidationException si Openpay no responde */
    public function reactivarRenovacion(User $usuario): bool
    {
        $viva = ! $this->api->conSuscripciones() ? null : $this->suscripciones->vivaDe($usuario, $this->nombre());

        return $viva ? (bool) $this->contraOpenpay(fn () => $this->suscripciones->reactivar($viva)) : false;
    }

    /**
     * Una llamada a Openpay desde una acción de la persona: si falla, un
     * mensaje claro en vez de un 500.
     *
     * @throws ValidationException
     */
    private function contraOpenpay(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (ErrorDeOpenpay $e) {
            Log::error('Openpay: no se pudo cambiar la renovación.', $e->contexto());

            throw ValidationException::withMessages([
                'plan' => 'No pudimos cambiar la renovación en este momento. Intenta de nuevo en unos minutos.',
            ]);
        }
    }

    // ── Cliente y tarjeta guardada (Openpay) ────────────────────────────────

    /**
     * El cliente de esta persona en Openpay; se crea la primera vez.
     *
     * `external_id` lleva el entorno (`local`, `staging`…) porque varios
     * entornos comparten el mismo sandbox y el usuario 2 de uno no es el de
     * otro. Si la creación responde 2003 («ya existe») —la red se cortó
     * después de crearlo—, se recupera por ese mismo `external_id`.
     *
     * @throws ValidationException
     */
    public function clienteDe(User $usuario): ClientePasarela
    {
        $fila = ClientePasarela::where('user_id', $usuario->id)->where('pasarela', $this->nombre())->first();

        if ($fila) {
            return $fila;
        }

        $externo = 'nodico-'.app()->environment().'-'.$usuario->id;
        $ip = $this->ipDelCliente();

        try {
            $cliente = $this->api->crearCliente([
                'external_id'      => $externo,
                'requires_account' => false,
                ...$this->datosDelCliente($usuario),
            ], $ip);
        } catch (ErrorDeOpenpay $e) {
            $cliente = $e->codigo === 2003 ? $this->recuperarCliente($externo, $ip) : null;

            if (! $cliente) {
                Log::error('Openpay: no se pudo crear el cliente.', ['usuario' => $usuario->id, ...$e->contexto()]);

                throw ValidationException::withMessages(['plan' => MensajesDeOpenpay::paraError($e)]);
            }
        }

        Log::info('Openpay: cliente creado.', ['usuario' => $usuario->id, 'cliente' => $cliente['id']]);

        return ClientePasarela::create([
            'user_id'    => $usuario->id,
            'pasarela'   => $this->nombre(),
            'cliente_id' => $cliente['id'],
        ]);
    }

    /**
     * Guarda la tarjeta del token en Openpay y deja en Nódico solo su id y lo
     * que se le enseña a la persona. Devuelve el id de la tarjeta, o `null` si
     * la tarjeta ya estaba guardada en ese cliente (2002): entonces se cobra
     * con el token y se conserva la que ya había.
     *
     * @throws ValidationException
     */
    private function guardarTarjeta(ClientePasarela $cliente, string $token, string $deviceSessionId): ?string
    {
        try {
            $tarjeta = $this->api->guardarTarjeta($cliente->cliente_id, $token, $deviceSessionId, $this->ipDelCliente());
        } catch (ErrorDeOpenpay $e) {
            if ($e->codigo === 2002) {
                Log::info('Openpay: la tarjeta ya estaba guardada en el cliente; se cobra con el token.', ['cliente' => $cliente->id]);

                return null;
            }

            Log::warning('Openpay: no se pudo guardar la tarjeta.', ['cliente' => $cliente->id, ...$e->contexto()]);

            throw ValidationException::withMessages(['plan' => MensajesDeOpenpay::paraError($e)]);
        }

        $cliente->update([
            'tarjeta_id'       => $tarjeta['id'],
            'tarjeta_marca'    => $tarjeta['brand'] ?? null,
            'tarjeta_ultimos4' => isset($tarjeta['card_number']) ? substr((string) $tarjeta['card_number'], -4) : null,
            'tarjeta_vence'    => isset($tarjeta['expiration_month'], $tarjeta['expiration_year'])
                ? "{$tarjeta['expiration_month']}/{$tarjeta['expiration_year']}" : null,
        ]);

        Log::info('Openpay: tarjeta guardada.', ['cliente' => $cliente->id, 'marca' => $cliente->tarjeta_marca]);

        return $tarjeta['id'];
    }

    private function recuperarCliente(string $externo, string $ip): ?array
    {
        try {
            return $this->api->buscarClientePorExterno($externo, $ip);
        } catch (ErrorDeOpenpay) {
            return null;
        }
    }

    // ── Apoyo ───────────────────────────────────────────────────────────────

    private function esBbva(): bool
    {
        return $this->nombre() === 'bbva';
    }

    /** Un candado por persona: un doble clic no crea dos cargos ni dos clientes. */
    private function conCandado(User $usuario, callable $fn): mixed
    {
        return Cache::lock("pasarela:cobrar:{$usuario->id}", 30)->block(15, $fn);
    }

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
            ->where('pasarela', $this->nombre())
            ->where('origen', $origen)
            ->esperandoAlBanco()
            ->whereNotNull('url_pago')
            ->where('created_at', '>=', now()->subMinutes((int) config('pagos.minutos_para_retomar', 30)))
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

        Log::info('Pasarela: se retoma un cargo pendiente en vez de crear otro.', ['cargo' => $pendiente->id]);

        return ['modo' => 'redireccion', 'url' => $pendiente->url_pago, 'cargo_id' => $pendiente->id];
    }

    /**
     * @param  array<string, string>  $conTarjeta  vacío = formulario de la pasarela;
     *                                             con `source_id` = token o tarjeta guardada.
     * @return array{modo: string, cargo_id: int, url?: string}
     *
     * @throws ValidationException
     */
    private function crearCargo(
        User $usuario, Plane $plan, string $origen, array $conTarjeta = [], ?string $clienteId = null, bool $suscribir = false,
    ): array {
        $ip = $this->ipDelCliente();

        // La fila va primero: si la red se corta después de que la pasarela
        // cree el cargo, sabemos qué se pidió y por cuánto.
        $cargo = CargoPasarela::create([
            'user_id'             => $usuario->id,
            'plan_id'             => $plan->id,
            'pasarela'            => $this->nombre(),
            'order_id'            => $this->nuevoOrderId(),
            'cliente_pasarela_id' => $clienteId,
            // Primer periodo de una suscripción: al confirmarse, se da de alta.
            'suscribir'           => $suscribir,
            'importe'             => Importe::enPesos($plan),
            'moneda'              => 'MXN',
            'estado'              => CargoPasarela::CREANDO,
            'origen'              => $origen,
            'ip_cliente'          => $ip,
        ]);

        $regreso = $origen === 'app' ? route('pago.banco.regreso-app') : route('portal.pago.banco.regreso');
        $conTresDs = $conTarjeta && $this->api->ajuste('tres_d_secure') === 'siempre';

        try {
            $respuesta = $this->enviarCargo($cargo, $usuario, $plan, $conTarjeta, $clienteId, $regreso, $conTresDs, $ip);
        } catch (ErrorDeOpenpay $e) {
            // El antifraude de Openpay rechazó por riesgo: se reintenta con
            // 3-D Secure y la misma tarjeta. Nuevo `order_id`, porque el del
            // intento rechazado ya quedó registrado.
            if ($e->codigo === 3005 && $conTarjeta && ! $conTresDs && $this->api->ajuste('tres_d_secure') === 'si_hace_falta') {
                Log::info('Openpay: rechazo por riesgo (3005); se reintenta con 3-D Secure.', ['cargo' => $cargo->id]);
                $cargo->update(['order_id' => $this->nuevoOrderId()]);

                try {
                    $respuesta = $this->enviarCargo($cargo, $usuario, $plan, $conTarjeta, $clienteId, $regreso, true, $ip);
                } catch (ErrorDeOpenpay $e2) {
                    $this->fallar($cargo, $e2);
                }
            } else {
                $this->fallar($cargo, $e);
            }
        }

        $transaccion = $respuesta['id'] ?? null;
        $url = $respuesta['payment_method']['url'] ?? null;
        $estado = strtolower((string) ($respuesta['status'] ?? ''));

        // Con el formulario de la pasarela siempre hay URL; con tarjeta, solo
        // si hubo 3-D Secure.
        if (! $transaccion || (! $url && ! $conTarjeta)) {
            $cargo->update(['estado' => CargoPasarela::FALLIDO, 'error_mensaje' => 'Respuesta sin id o sin URL de pago.']);
            Log::error('Pasarela: el cargo se creó sin id o sin URL de pago.', ['cargo' => $cargo->id, 'respuesta' => $respuesta]);

            throw ValidationException::withMessages(['plan' => MensajesDeOpenpay::paraCodigo(null)]);
        }

        $cargo->update([
            'transaccion_id'  => $transaccion,
            'estado'          => CargoPasarela::PENDIENTE,
            'estado_pasarela' => $estado,
            'url_pago'        => $url,
        ]);

        Log::info('Pasarela: cargo creado.', [
            'pasarela' => $this->nombre(), 'cargo' => $cargo->id, 'transaccion' => $transaccion, 'plan' => $plan->id,
            'importe' => $cargo->importe, 'captura' => $conTarjeta ? 'tarjeta' : 'formulario', 'estado_pasarela' => $estado,
        ]);

        if ($url) {
            return ['modo' => 'redireccion', 'url' => $url, 'cargo_id' => $cargo->id];
        }

        // Sin 3-D Secure el cargo ya se resolvió. Aun así, lo que diga la
        // respuesta de creación no activa nada: se consulta, como siempre.
        $this->consultarSinRomper($cargo);

        if ($cargo->estado === CargoPasarela::FALLIDO) {
            throw ValidationException::withMessages(['plan' => MensajesDeOpenpay::paraCargoFallido(
                $cargo->error_mensaje,
                is_numeric($cargo->error_codigo) ? (int) $cargo->error_codigo : null,
            )]);
        }

        return ['modo' => 'confirmando', 'cargo_id' => $cargo->id];
    }

    /**
     * Arma y manda la petición del cargo según la plataforma y el modo.
     *
     * @throws ErrorDeOpenpay
     */
    private function enviarCargo(
        CargoPasarela $cargo, User $usuario, Plane $plan, array $conTarjeta,
        ?string $clienteId, string $regreso, bool $conTresDs, string $ip,
    ): array {
        $datos = [
            'amount'      => $cargo->importe,
            'description' => Str::limit("Nódico — {$plan->nombre}", 250, ''),
            'currency'    => 'MXN',
            'order_id'    => $cargo->order_id,
        ];

        // El cargo a nivel cliente ya sabe de quién es; a nivel comercio se
        // mandan los datos de la persona («no se creará una cuenta»).
        if (! $clienteId) {
            $datos['customer'] = $this->datosDelCliente($usuario);
        }

        if ($this->esBbva()) {
            // BBVA: afiliación siempre; 3-D Secure lo aplica su servidor.
            $datos += ['affiliation_bbva' => $this->api->afiliacion(), 'redirect_url' => $regreso];
        } elseif (! $conTarjeta) {
            // Openpay, «cargo con redireccionamiento»: su formulario.
            // https://documents.openpay.mx/docs/api/index.html#con-redireccionamiento
            $datos += ['method' => 'card', 'confirm' => false, 'send_email' => false, 'redirect_url' => $regreso];
        } elseif ($conTresDs) {
            $datos += ['use_3d_secure' => true, 'redirect_url' => $regreso];
        }

        return $this->api->crearCargo([...$datos, ...$conTarjeta], $ip, $clienteId);
    }

    /**
     * @throws ValidationException
     */
    private function fallar(CargoPasarela $cargo, ErrorDeOpenpay $e): never
    {
        $cargo->update([
            'estado'        => CargoPasarela::FALLIDO,
            'error_codigo'  => $e->codigo !== null ? (string) $e->codigo : null,
            'error_mensaje' => $e->descripcion,
        ]);
        Log::error('Pasarela: no se pudo crear el cargo.', ['pasarela' => $this->nombre(), 'cargo' => $cargo->id, ...$e->contexto()]);

        throw ValidationException::withMessages(['plan' => MensajesDeOpenpay::paraError($e)]);
    }

    /**
     * Único entre TODAS las transacciones del comercio. Aleatorio y no
     * consecutivo: local y staging comparten el mismo sandbox.
     */
    private function nuevoOrderId(): string
    {
        return 'nod-'.Str::lower((string) Str::ulid());
    }

    /**
     * Los datos de la persona para la pasarela. Nódico guarda el nombre
     * completo en un solo campo.
     *
     * @return array<string, string>
     */
    private function datosDelCliente(User $usuario): array
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
     * La IP que va en `X-Forwarded-For` para el antifraude.
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
        } catch (ErrorDeOpenpay $e) {
            Log::warning('Pasarela: no se pudo consultar el cargo; se reintentará.', ['cargo' => $cargo->id, ...$e->contexto()]);
        }
    }
}
