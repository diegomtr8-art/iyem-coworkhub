<?php

namespace App\Http\Controllers;

use App\Models\CargoPasarela;
use App\Servicios\Pagos\Bbva\ConfirmadorDeCargo;
use App\Servicios\Pagos\Bbva\ErrorDeBbva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * BBVA regresa aquí cuando el pago se abrió desde la app.
 *
 * No hay sesión web, y no hace falta: el confirmador no se fía de quién llama.
 * Solo actúa sobre un cargo que Nódico creó (con su dueño, plan e importe
 * guardados), y según lo que responda la API de BBVA. Pedir esta URL con un
 * id inventado no hace nada. Al final se devuelve a la app, que pregunta el
 * estado con su token.
 */
class RegresoBbvaAppController extends Controller
{
    public function __invoke(Request $request, ConfirmadorDeCargo $confirmador): RedirectResponse
    {
        $id = (string) $request->query('id', '');

        $cargo = $id !== '' ? CargoPasarela::where('pasarela', 'bbva')
            ->where('origen', 'app')
            ->where('transaccion_id', $id)
            ->first() : null;

        if (! $cargo) {
            Log::warning('BBVA: regreso a la app con un id desconocido.', ['id' => mb_substr($id, 0, 60)]);

            return redirect()->away(self::VUELTA_POR_DEFECTO.'?error=desconocido');
        }

        try {
            $confirmador->confirmar($cargo);
        } catch (ErrorDeBbva $e) {
            Log::warning('BBVA: no se pudo consultar el cargo al regresar a la app.', ['cargo' => $cargo->id, ...$e->contexto()]);
        }

        $vuelta = self::vueltaValida($cargo->url_vuelta) ?? self::VUELTA_POR_DEFECTO;
        $separador = str_contains($vuelta, '?') ? '&' : '?';

        return redirect()->away($vuelta.$separador.'cargo='.$cargo->id);
    }

    private const VUELTA_POR_DEFECTO = 'nodico://regreso-banco';

    /**
     * Solo se regresa a la app: `nodico://` (la app instalada) o `exp://` /
     * `exps://` (Expo Go en desarrollo). Cualquier otra cosa sería una
     * redirección abierta.
     */
    public static function vueltaValida(?string $url): ?string
    {
        if (! $url || strlen($url) > 500) {
            return null;
        }

        return preg_match('#^(nodico|exps?)://#i', $url) ? $url : null;
    }
}
