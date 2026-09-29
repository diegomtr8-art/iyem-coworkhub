<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CargoPasarela;
use App\Models\Plane;
use App\Servicios\Pagos\Bbva\ConfirmadorDeCargo;
use App\Servicios\Pagos\Bbva\ErrorDeBbva;
use App\Servicios\Pagos\Contratos\PasarelaDePagos;
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
 * - **BBVA:** la tarjeta se teclea en el formulario del banco. Aquí se crea el
 *   cargo, se manda a la persona allá y se la recibe de vuelta.
 *
 * En los dos casos, el número, el CVC y la fecha **nunca tocan este servidor**,
 * y la activación de la membresía **no** ocurre aquí por lo que diga el
 * navegador: la confirma el proveedor (webhook de Stripe, o la consulta del
 * cargo a la API de BBVA).
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
        ];

        // Sin llaves todavía, la pantalla se muestra en **vista previa**: se ve
        // el flujo completo dentro de Nódico con la tarjeta deshabilitada y su
        // motivo, sin llamar a ninguna pasarela.
        if (! $this->pasarela->disponible()) {
            return Inertia::render('Portal/Pago', [...$comunes, 'vistaPrevia' => true]);
        }

        // BBVA: el cargo se crea al pulsar «Pagar» (`iniciar`), no al ver la
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
     * BBVA: crea el cargo y manda a la persona al formulario del banco. Si ya
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
     * BBVA regresa aquí con `?id={transacción}`. El id es una **pista**, no una
     * prueba: solo se acepta si es de un cargo que Nódico creó para esta
     * persona, y lo que se hace depende de lo que responda la API al
     * consultarlo. Nunca se activa nada por haber llegado a esta URL.
     */
    public function regresoBbva(Request $request, ConfirmadorDeCargo $confirmador): RedirectResponse
    {
        $id = (string) $request->query('id', '');

        $cargo = $id !== '' ? CargoPasarela::where('user_id', $request->user()->id)
            ->where('pasarela', 'bbva')
            ->where('transaccion_id', $id)
            ->first() : null;

        if (! $cargo) {
            Log::warning('BBVA: regreso con un id que no es de un cargo de esta persona.', [
                'usuario' => $request->user()->id, 'id' => Str::limit($id, 60),
            ]);

            return redirect()->route('portal.suscripcion')
                ->with('error', 'No encontramos ese pago. Si se te cobró, escríbenos y lo revisamos.');
        }

        try {
            $confirmador->confirmar($cargo);
        } catch (ErrorDeBbva $e) {
            // La pantalla «confirmando» vuelve a preguntar; y si no, el proceso
            // programado.
            Log::warning('BBVA: no se pudo consultar el cargo al regresar.', ['cargo' => $cargo->id, ...$e->contexto()]);
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
