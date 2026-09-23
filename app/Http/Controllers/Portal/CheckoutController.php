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
use App\Servicios\Pagos\CobroConTarjeta;

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

        $intent = $this->cobro()->preparar($request->user(), $plan, exigirPrecio: false);

        return Inertia::render('Portal/Pago', [
            'plan'           => $datosPlan,
            'modo'           => $modo,
            'vistaPrevia'    => false,
            'clientSecret'   => $intent['client_secret'],
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
        $datos = $request->validate([
            'payment_method' => ['required', 'string'],
        ]);

        $pendiente = $this->cobro()->suscribir($request->user(), $plan, $datos['payment_method']);

        if ($pendiente !== null) {
            // El banco pide autenticación (3-D Secure): se manda a la pantalla de
            // Cashier que la resuelve, y luego a «confirmando».
            $intentId = \Illuminate\Support\Str::before($pendiente, '_secret_');

            return redirect()->route(
                'cashier.payment',
                [$intentId, 'redirect' => route('portal.pago.confirmando')]
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
        $activa = $this->cobro()->membresiaActiva($request->user());

        return response()->json(['activa' => $activa]);
    }

    private function hayClavesDeStripe(): bool
    {
        return $this->cobro()->disponible();
    }

    private function cobro(): CobroConTarjeta
    {
        return app(CobroConTarjeta::class);
    }
}
