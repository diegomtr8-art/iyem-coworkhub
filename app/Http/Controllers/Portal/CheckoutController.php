<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Plane;
use App\Models\Suscripcion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Cashier;

/**
 * Fase 4.A — el cobro ocurre **dentro** de Nódico, con Stripe Elements.
 *
 * El campo de tarjeta es un iframe servido por Stripe montado en nuestra propia
 * página: visualmente el pago pasa en Nódico, pero el número, el CVC y la fecha
 * **nunca tocan este servidor ni la base de datos**. Aquí solo se prepara el
 * intent (con la clave secreta, en el servidor) y se recibe de vuelta un
 * identificador de método de pago o el resultado del intent; los datos de la
 * tarjeta viajan directos del navegador a Stripe.
 *
 * La activación de la membresía **no** ocurre aquí: la hace el webhook (ver
 * StripeWebhookController). Esta pantalla solo cobra; la verdad la confirma
 * Stripe.
 */
class CheckoutController extends Controller
{
    /** Prepara el pago del plan elegido y muestra el formulario de tarjeta. */
    public function mostrar(Request $request, Plane $plan): Response
    {
        $modo = $plan->cobro_recurrente ? 'suscripcion' : 'pago_unico';
        $datosPlan = [
            'id'            => $plan->id,
            'nombre'        => $plan->nombre,
            'precio'        => (float) $plan->precio,
            'periodo_label' => $plan->periodo_label,
            'recurrente'    => $plan->cobro_recurrente,
            'color'         => $plan->color,
        ];

        // Sin claves de Stripe todavía, la pantalla se muestra en **vista previa**:
        // se ve el flujo completo dentro de Nódico, pero no se crea intent ni se
        // llama a Stripe. El campo de tarjeta y el cobro se activan solos en cuanto
        // se configuren las claves. Así ya no se sale del sitio a buy.stripe.com.
        if (! $this->hayClavesDeStripe()) {
            return Inertia::render('Portal/Pago', [
                'plan'         => $datosPlan,
                'modo'         => $modo,
                'vistaPrevia'  => true,
                'clientSecret' => null,
                'stripeKey'    => null,
                'volverA'      => route('portal.suscripcion'),
            ]);
        }

        $usuario = $request->user();
        $usuario->createOrGetStripeCustomer();

        if ($plan->cobro_recurrente) {
            // Suscripción: se guarda el método con un SetupIntent y luego se crea
            // la suscripción en el servidor (procesarSuscripcion).
            $intent = $usuario->createSetupIntent();
        } else {
            // Pago único: un PaymentIntent por el importe, con la metadata que el
            // webhook usará para saber a quién y qué plan activar.
            $intent = $usuario->stripe()->paymentIntents->create([
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
        }

        return Inertia::render('Portal/Pago', [
            'plan'           => $datosPlan,
            'modo'           => $modo,
            'vistaPrevia'    => false,
            'clientSecret'   => $intent->client_secret,
            'stripeKey'      => config('cashier.key'),
            'volverA'        => route('portal.suscripcion'),
        ]);
    }

    /**
     * Suscripción recurrente: con el método ya recogido por Elements, se crea la
     * suscripción en Stripe. El cobro dispara el webhook, que activa la
     * membresía de Nódico. Aquí NO se activa nada.
     */
    public function procesarSuscripcion(Request $request, Plane $plan): RedirectResponse
    {
        $this->verificarConfigurado($plan);

        $datos = $request->validate([
            'payment_method' => ['required', 'string'],
        ]);

        $usuario = $request->user();

        try {
            $usuario->newSubscription('default', $plan->stripe_price_id)
                ->create($datos['payment_method']);
        } catch (\Laravel\Cashier\Exceptions\IncompletePayment $e) {
            // El banco pide autenticación (3-D Secure): se manda a la pantalla de
            // Cashier que la resuelve, y luego a «confirmando».
            return redirect()->route(
                'cashier.payment',
                [$e->payment->id, 'redirect' => route('portal.pago.confirmando')]
            );
        }

        return redirect()->route('portal.pago.confirmando');
    }

    /**
     * Página de retorno: **solo** dice «estamos confirmando» y consulta el
     * estado. Nunca decide si el pago fue bueno; eso lo hace el webhook.
     */
    public function confirmando(): Response
    {
        return Inertia::render('Portal/PagoConfirmando');
    }

    /** Estado de la membresía, para que la página de confirmación deje de esperar. */
    public function estado(Request $request)
    {
        $activa = $request->user()->suscripciones()
            ->where('estatus', 'Activa')
            ->where('fecha_fin', '>=', now()->toDateString())
            ->exists();

        return response()->json(['activa' => $activa]);
    }

    /**
     * Un plan sin precio de Stripe no se puede cobrar dentro del sitio. Hasta que
     * se configure, el sitio cae al enlace de pago de respaldo (`stripe_url`).
     */
    private function verificarConfigurado(Plane $plan): void
    {
        $faltaPrecio = $plan->cobro_recurrente && ! $plan->stripe_price_id;

        if ($faltaPrecio || ! $this->hayClavesDeStripe()) {
            throw ValidationException::withMessages([
                'plan' => 'El cobro en línea todavía no está disponible para este plan. '
                    . 'Escríbenos y lo activamos.',
            ]);
        }
    }

    /** Hay claves de Stripe si están tanto la publishable como la secreta. */
    private function hayClavesDeStripe(): bool
    {
        return (bool) config('cashier.key') && (bool) config('cashier.secret');
    }
}
