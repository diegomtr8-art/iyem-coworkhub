<?php

namespace App\Http\Controllers;

use App\Enums\RolUsuario;
use App\Exceptions\ErrorDeApi;
use App\Models\Plane;
use App\Models\User;
use App\Servicios\Pagos\Contratos\PasarelaDePagos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Migración a Openpay — paso 6: el pago con tarjeta de la app se hace **en la
 * página web de Nódico**, no dentro de la app (decisión de Diego,
 * 29-sep-2026). La app abre el navegador del teléfono en la pantalla de pago
 * del portal, la persona paga ahí (con la tarjeta tecleada en Nódico y, si
 * hace falta, el 3-D Secure de su banco) y vuelve a la app.
 *
 * Para no pedirle que inicie sesión otra vez en el navegador, la app pide un
 * **enlace de un solo uso** con su token: vence en 5 minutos, solo sirve para
 * esa persona y ese plan, y en caché se guarda su huella (sha256), no el
 * enlace. Al abrirlo, se inicia la sesión web y se va directo a pagar.
 */
class PagoDesdeAppController extends Controller
{
    private const MINUTOS = 5;

    /** API (token de la app): pide el enlace para pagar un plan en la web. */
    public function enlace(Request $request, PasarelaDePagos $pasarela): JsonResponse
    {
        $datos = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:planes,id'],
            'vuelta'  => ['nullable', 'string', 'max:500'],
        ]);

        $plan = Plane::publicos()->whereKey($datos['plan_id'])->first();

        if (! $plan) {
            throw new ErrorDeApi(422, 'plan_no_disponible', 'Ese plan ya no está disponible.');
        }

        if (! $pasarela->disponiblePara($plan)) {
            throw new ErrorDeApi(409, 'tarjeta_no_disponible', 'El pago con tarjeta no está disponible por ahora. Paga con transferencia o en caja.');
        }

        $token = Str::random(64);

        Cache::put(self::clave($token), [
            'user_id' => $request->user()->id,
            'plan_id' => $plan->id,
            'vuelta'  => RegresoDelBancoAppController::vueltaValida($datos['vuelta'] ?? null),
        ], now()->addMinutes(self::MINUTOS));

        return response()->json(['data' => [
            'url'        => route('pago.desde-app', $token),
            'vence_en'   => self::MINUTOS * 60,
            'pasarela'   => $pasarela->nombre(),
        ]]);
    }

    /**
     * Web: canjea el enlace (una sola vez), inicia la sesión y manda a la
     * pantalla de pago del plan. Si venció o ya se usó, se dice claro.
     */
    public function entrar(Request $request, string $token): RedirectResponse
    {
        $datos = Cache::pull(self::clave($token));
        $usuario = $datos ? User::find($datos['user_id']) : null;

        if (! $usuario || $usuario->rol !== RolUsuario::Miembro) {
            Log::info('Pago desde la app: enlace vencido, usado o inválido.');

            return redirect()->route('login')
                ->with('info', 'El enlace de pago venció. Vuelve a la app y toca «Pagar con tarjeta» otra vez.');
        }

        // Si en ese navegador había otra sesión, se cierra: se paga como
        // quien pidió el enlace.
        $web = Auth::guard('web');

        if ($web->check() && $web->id() !== $usuario->id) {
            $web->logout();
        }

        // La sesión es la del portal (guardia `web`), no la de la API.
        $web->login($usuario);
        $request->session()->regenerate();
        $request->session()->put('pago_desde_app', [
            'vuelta' => $datos['vuelta'] ?? 'nodico://regreso-banco',
        ]);

        Log::info('Pago desde la app: sesión web abierta para pagar.', ['usuario' => $usuario->id, 'plan' => $datos['plan_id']]);

        return redirect()->route('portal.contratar.tarjeta', $datos['plan_id']);
    }

    private static function clave(string $token): string
    {
        return 'pago-desde-app:'.hash('sha256', $token);
    }
}
