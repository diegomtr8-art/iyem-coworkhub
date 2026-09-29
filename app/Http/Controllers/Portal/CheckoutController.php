<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CargoPasarela;
use App\Models\Plane;
use App\Servicios\Pagos\Openpay\ConfirmadorDeCargo;
use App\Servicios\Pagos\Openpay\ErrorDeOpenpay;
use App\Servicios\Pagos\Contratos\PasarelaDePagos;
use App\Servicios\Pagos\PasarelaOpenpay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as RespuestaHttp;

/**
 * Cobro con tarjeta desde el portal, con la pasarela que diga
 * `config('pagos.pasarela')`.
 *
 * - **Stripe:** el campo de tarjeta es un iframe de Stripe (Elements) montado
 *   en nuestra página; aquí solo se prepara el intent.
 * - **Openpay / Ecommerce BBVA:** la tarjeta se teclea en Nódico (openpay.js,
 *   token) o en el formulario de la pasarela. Aquí se crea el
 *   cargo, se manda a la persona allá y se la recibe de vuelta.
 *
 * En los dos casos, el número, el CVC y la fecha **nunca tocan este servidor**,
 * y la activación de la membresía **no** ocurre aquí por lo que diga el
 * navegador: la confirma el proveedor (webhook de Stripe, o la consulta del
 * cargo a la API de la pasarela).
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly PasarelaDePagos $pasarela)
    {
    }

    /** Muestra la pantalla de pago del plan elegido. */
    public function mostrar(Request $request, Plane $plan): Response
    {
        $modo = $plan->cobro_recurrente && $this->pasarela->renuevaSola($plan) ? 'suscripcion' : 'pago_unico';

        $comunes = [
            'plan' => [
                'id'            => $plan->id,
                'nombre'        => $plan->nombre,
                'precio'        => (float) $plan->precio,
                'periodo_label' => $plan->periodo_label,
                'recurrente'    => $plan->cobro_recurrente,
                'renueva_sola'  => $this->pasarela->renuevaSola($plan),
                'color'         => $plan->color,
            ],
            'modo'         => $modo,
            'pasarela'     => $this->pasarela->nombre(),
            'etiqueta'     => $this->pasarela->etiqueta(),
            'volverA'      => route('portal.suscripcion'),
            'clientSecret' => null,
            'stripeKey'    => null,
            // Openpay/BBVA: `token` = la tarjeta se teclea aquí (openpay.js);
            // `vpos` = en el formulario del banco.
            'captura'      => $this->pasarela instanceof PasarelaOpenpay && $this->pasarela->capturaEnNodico() ? 'token' : 'vpos',
            'openpay'      => $this->pasarela instanceof PasarelaOpenpay ? $this->pasarela->datosParaElNavegador() : null,
            'guardaTarjeta' => $this->pasarela instanceof PasarelaOpenpay && $this->pasarela->guardaTarjeta($plan),
        ];

        // Sin llaves todavía, la pantalla se muestra en **vista previa**: se ve
        // el flujo completo dentro de Nódico con la tarjeta deshabilitada y su
        // motivo, sin llamar a ninguna pasarela.
        if (! $this->pasarela->disponible()) {
            return Inertia::render('Portal/Pago', [...$comunes, 'vistaPrevia' => true]);
        }

        // Openpay/BBVA: el cargo se crea al pulsar «Pagar», no al ver la
        // página: recargarla no debe crear cargos.
        if ($this->pasarela->nombre() !== 'stripe') {
            return Inertia::render('Portal/Pago', [...$comunes, 'vistaPrevia' => false]);
        }

        $intent = $this->pasarela->preparar($request->user(), $plan, exigirConfiguracion: false);

        return Inertia::render('Portal/Pago', [
            ...$comunes,
            'vistaPrevia'  => false,
            'clientSecret' => $intent['client_secret'],
            'stripeKey'    => $this->pasarela->llavePublica(),
        ]);
    }

    /**
     * Formulario de la pasarela: crea el cargo y manda a la persona allá. Si ya
     * hay uno reciente del mismo plan esperando, la manda a ese mismo.
     */
    public function iniciar(Request $request, Plane $plan): RespuestaHttp
    {
        abort_if($this->pasarela->nombre() === 'stripe', 404);

        $cobro = $this->pasarela->preparar($request->user(), $plan, origen: 'web');

        // Redirección fuera del sitio: Inertia necesita `location`, no un 302.
        return Inertia::location($cobro['url']);
    }

    /**
     * Tarjeta tecleada en Nódico: openpay.js ya la cambió por un
     * token en el navegador; aquí solo llegan el token y el identificador del
     * dispositivo (antifraude). Si el banco pide 3-D Secure, se manda a su
     * página; si no, directo a «confirmando», que consulta el cargo.
     */
    public function cobrarConToken(Request $request, Plane $plan): RespuestaHttp
    {
        abort_unless($this->pasarela instanceof PasarelaOpenpay && $this->pasarela->capturaEnNodico(), 404);

        $datos = $request->validate([
            'token_id'          => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'device_session_id' => ['required', 'string', 'max:255'],
        ], [
            'device_session_id.required' => 'No pudimos verificar tu dispositivo. Recarga la página y vuelve a intentarlo.',
        ]);

        $cobro = $this->pasarela->cobrarConToken($request->user(), $plan, $datos['token_id'], $datos['device_session_id']);

        if ($cobro['modo'] === 'redireccion') {
            return Inertia::location($cobro['url']);
        }

        return redirect()->route('portal.pago.confirmando', ['cargo' => $cobro['cargo_id']]);
    }

    /**
     * Stripe, suscripción recurrente: con el método ya recogido por Elements,
     * se crea la suscripción. El cobro dispara el webhook, que activa la
     * membresía de Nódico. Aquí NO se activa nada.
     */
    public function procesarSuscripcion(Request $request, Plane $plan): RedirectResponse
    {
        $datos = $request->validate([
            'payment_method' => ['required', 'string'],
        ]);

        $pendiente = $this->pasarela->suscribir($request->user(), $plan, $datos['payment_method']);

        if ($pendiente !== null) {
            // El banco pide autenticación (3-D Secure): se manda a la pantalla de
            // Cashier que la resuelve, y luego a «confirmando».
            $intentId = Str::before($pendiente, '_secret_');

            return redirect()->route(
                'cashier.payment',
                [$intentId, 'redirect' => route('portal.pago.confirmando')]
            );
        }

        return redirect()->route('portal.pago.confirmando');
    }

    /**
     * La pasarela (tras el 3-D Secure o su formulario) regresa aquí con
     * `?id={transacción}`. El id es una **pista**, no una
     * prueba: solo se acepta si es de un cargo que Nódico creó para esta
     * persona, y lo que se hace depende de lo que responda la API al
     * consultarlo. Nunca se activa nada por haber llegado a esta URL.
     */
    public function regresoDelBanco(Request $request, ConfirmadorDeCargo $confirmador): RedirectResponse
    {
        $id = (string) $request->query('id', '');

        $cargo = $id !== '' ? CargoPasarela::where('user_id', $request->user()->id)
            ->whereIn('pasarela', ['openpay', 'bbva'])
            ->where('transaccion_id', $id)
            ->first() : null;

        if (! $cargo) {
            Log::warning('Pasarela: regreso con un id que no es de un cargo de esta persona.', [
                'usuario' => $request->user()->id, 'id' => Str::limit($id, 60),
            ]);

            return redirect()->route('portal.suscripcion')
                ->with('error', 'No encontramos ese pago. Si se te cobró, escríbenos y lo revisamos.');
        }

        try {
            $confirmador->confirmar($cargo);
        } catch (ErrorDeOpenpay $e) {
            // La pantalla «confirmando» vuelve a preguntar; y si no, el proceso
            // programado.
            Log::warning('Pasarela: no se pudo consultar el cargo al regresar.', ['cargo' => $cargo->id, ...$e->contexto()]);
        }

        return redirect()->route('portal.pago.confirmando', ['cargo' => $cargo->id]);
    }

    /**
     * Página de retorno: **solo** dice «estamos confirmando» y consulta el
     * estado. Nunca decide si el pago fue bueno.
     */
    public function confirmando(Request $request): Response
    {
        return Inertia::render('Portal/PagoConfirmando', [
            'cargo'    => $request->integer('cargo') ?: null,
            'etiqueta' => $this->pasarela->etiqueta(),
        ]);
    }

    /** Estado del pago, para que la página de confirmación deje de esperar. */
    public function estado(Request $request)
    {
        return response()->json(
            $this->pasarela->estadoDelCobro($request->user(), $request->integer('cargo') ?: null)
        );
    }
}
