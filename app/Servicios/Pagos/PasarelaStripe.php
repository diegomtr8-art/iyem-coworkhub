<?php

namespace App\Servicios\Pagos;

use App\Models\Plane;
use App\Models\User;
use App\Servicios\Pagos\Contratos\PasarelaDePagos;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Exceptions\IncompletePayment;

/**
 * Cobro con tarjeta vía Stripe: una sola copia para la web (Elements) y la app
 * (hoja de pago nativa).
 *
 * Los datos de la tarjeta **nunca** pasan por Nódico: aquí solo se prepara el
 * intent con la clave secreta y se recibe de vuelta un identificador. Y la
 * activación de la membresía **no** ocurre aquí: la hace el webhook
 * (`StripeWebhookController`). Esto solo cobra; la verdad la confirma Stripe.
 *
 * Antes se llamaba `CobroConTarjeta`; su comportamiento no cambió al ponerse
 * detrás de `PasarelaDePagos` (migración a BBVA, docs/PAGOS-BBVA.md).
 */
class PasarelaStripe implements PasarelaDePagos
{
    use ConsultaMembresiaActiva;

    public function nombre(): string
    {
        return 'stripe';
    }

    public function etiqueta(): string
    {
        return 'Stripe';
    }

    /** Hay claves de Stripe si están tanto la publicable como la secreta. */
    public function disponible(): bool
    {
        return (bool) config('cashier.key') && (bool) config('cashier.secret');
    }

    /** Recurrente sin precio de Stripe: solo por referencia. */
    public function disponiblePara(Plane $plan): bool
    {
        return $this->disponible() && (! $plan->cobro_recurrente || filled($plan->stripe_price_id));
    }

    public function renuevaSola(Plane $plan): bool
    {
        return (bool) $plan->cobro_recurrente;
    }

    public function llavePublica(): ?string
    {
        return $this->disponible() ? config('cashier.key') : null;
    }

    /**
     * Prepara el intent del plan elegido.
     *
     * Plan recurrente: un SetupIntent que guarda la tarjeta; la suscripción se
     * crea después en el servidor (`suscribir`). Pago único: un PaymentIntent
     * por el importe con la metadata que el webhook usa para activar.
     *
     * @return array{modo: string, tipo_intent: string, client_secret: string}
     *
     * @throws ValidationException
     */
    public function preparar(User $usuario, Plane $plan, bool $exigirConfiguracion = true, string $origen = 'web'): array
    {
        // La web enseña el formulario aunque al plan recurrente le falte el
        // precio de Stripe y avisa al enviar, como hacía antes; la app prefiere
        // saberlo antes de abrir la hoja de pago.
        if ($exigirConfiguracion) {
            $this->verificarConfigurado($plan);
        }

        $usuario->createOrGetStripeCustomer();

        // Antes de pedir la tarjeta: si ya se cobra sola, no tiene sentido.
        if ($plan->cobro_recurrente && $exigirConfiguracion) {
            $this->suscripcionEnCurso($usuario);
        }

        if ($plan->cobro_recurrente) {
            $intent = $usuario->createSetupIntent();

            return ['modo' => 'suscripcion', 'tipo_intent' => 'setup', 'client_secret' => $intent->client_secret];
        }

        $intent = $usuario->stripe()->paymentIntents->create([
            // Stripe cobra en CENTAVOS enteros. Solo Stripe: BBVA cobra en pesos
            // (ver `Importe`).
            'amount'   => (int) round($plan->precio * 100),
            'currency' => config('cashier.currency', 'mxn'),
            'customer' => $usuario->stripe_id,
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'plan_id' => (string) $plan->id,
                'user_id' => (string) $usuario->id,
                'tipo'    => 'pago_unico_nodico',
            ],
        ]);

        return ['modo' => 'pago_unico', 'tipo_intent' => 'payment', 'client_secret' => $intent->client_secret];
    }

    /**
     * Crea la suscripción recurrente con el método de pago ya guardado.
     *
     * Devuelve `null` si quedó creada, o el `client_secret` del pago que el
     * banco quiere autenticar (3-D Secure) para que la pantalla lo resuelva.
     *
     * @throws ValidationException
     */
    public function suscribir(User $usuario, Plane $plan, string $metodoDePago): ?string
    {
        $this->verificarConfigurado($plan);

        // Nunca dos suscripciones recurrentes a la vez. Si la red se cortó
        // después de crearla y la persona vuelve a pagar, o si falló la
        // autenticación del banco, se retoma la que ya existe en vez de crear
        // otra que cobraría cada mes a la misma tarjeta.
        $pendiente = $this->suscripcionEnCurso($usuario);

        if ($pendiente !== false) {
            return $pendiente;
        }

        try {
            $usuario->newSubscription('default', $plan->stripe_price_id)->create($metodoDePago);
        } catch (IncompletePayment $e) {
            return $e->payment->client_secret;
        }

        return null;
    }

    /**
     * Qué hacer si ya hay una suscripción recurrente.
     *
     * - Esperando la autenticación del banco: devuelve el `client_secret` de su
     *   pago pendiente para terminarlo, sin crear otra.
     * - Activa y sin terminar: se rechaza; renovar no hace falta, se cobra sola.
     * - Ninguna (o terminada): `false`, se puede crear.
     *
     * @throws ValidationException
     */
    public function suscripcionEnCurso(User $usuario): string|false
    {
        $actual = $usuario->subscription('default');

        if (! $actual || $actual->ended()) {
            return false;
        }

        if ($actual->hasIncompletePayment()) {
            $pago = $actual->latestPayment();

            if ($pago && $pago->requiresAction()) {
                return $pago->clientSecret();
            }
        }

        if ($actual->valid()) {
            throw ValidationException::withMessages([
                'plan' => 'Ya tienes una membresía con renovación automática: se cobra sola cada periodo. '
                    . 'Para cambiar de plan, escríbenos o cancela la renovación primero.',
            ]);
        }

        return false;
    }

    /**
     * El método de pago de un SetupIntent **de este cliente** ya confirmado.
     *
     * La app manda solo el id del SetupIntent; se comprueba en Stripe que es
     * de esta persona antes de cobrar con él. Sin eso, un id ajeno bastaría
     * para suscribirse con la tarjeta de otro.
     *
     * @throws ValidationException
     */
    public function metodoDelSetupIntent(User $usuario, string $setupIntentId): string
    {
        $intent = $usuario->stripe()->setupIntents->retrieve($setupIntentId);

        if ($intent->customer !== $usuario->stripe_id || $intent->status !== 'succeeded' || ! $intent->payment_method) {
            throw ValidationException::withMessages([
                'setup_intent_id' => 'No pudimos confirmar tu tarjeta. Vuelve a intentarlo.',
            ]);
        }

        return is_string($intent->payment_method) ? $intent->payment_method : $intent->payment_method->id;
    }

    /**
     * ¿Ya hay membresía vigente? La pantalla «confirmando» lo pregunta hasta
     * que el webhook llega. Con Stripe no hay cargo propio que consultar.
     */
    public function estadoDelCobro(User $usuario, ?int $cargoId = null): array
    {
        return ['activa' => $this->membresiaActiva($usuario)];
    }

    /** Solo datos locales (los que Cashier guarda en `users`): no llama a la API de Stripe. */
    public function estadoDeRenovacion(User $usuario): array
    {
        $stripe = $usuario->subscription('default');

        return [
            'metodo_pago'          => $usuario->pm_last_four
                ? ['marca' => $usuario->pm_type, 'ultimos4' => $usuario->pm_last_four]
                : null,
            'tiene_recurrente'     => (bool) $stripe,
            'renovacion_activa'    => $stripe ? $stripe->active() && ! $stripe->canceled() : false,
            'en_periodo_de_gracia' => $stripe ? $stripe->onGracePeriod() : false,
        ];
    }

    public function cancelarRenovacion(User $usuario): bool
    {
        $stripe = $usuario->subscription('default');

        if (! $stripe || $stripe->canceled()) {
            return false;
        }

        $stripe->cancel();

        return true;
    }

    public function reactivarRenovacion(User $usuario): bool
    {
        $stripe = $usuario->subscription('default');

        if (! $stripe || ! $stripe->onGracePeriod()) {
            return false;
        }

        $stripe->resume();

        return true;
    }

    /**
     * Un plan recurrente sin precio de Stripe no se puede cobrar dentro del
     * sitio. Hasta que se configure, se cae al enlace de respaldo (`stripe_url`).
     *
     * @throws ValidationException
     */
    public function verificarConfigurado(Plane $plan): void
    {
        $faltaPrecio = $plan->cobro_recurrente && ! $plan->stripe_price_id;

        if ($faltaPrecio || ! $this->disponible()) {
            throw ValidationException::withMessages([
                'plan' => 'El cobro en línea todavía no está disponible para este plan. '
                    . 'Escríbenos y lo activamos.',
            ]);
        }
    }
}
