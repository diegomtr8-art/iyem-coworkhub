<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica la firma del agente de acceso (servicio a servicio, sin sesión).
 *
 * El agente firma cada envío con HMAC-SHA256 sobre `"<timestamp>.<cuerpo_crudo>"`
 * y el secreto compartido, y manda `X-Agente-Timestamp` y `X-Agente-Firma`. Aquí
 * se recomputa con el mismo secreto y se compara con `hash_equals` (tiempo
 * constante). Además, el timestamp debe caer dentro de la ventana anti-replay,
 * para que una petición capturada no se pueda reenviar más tarde.
 *
 * Reglas duras:
 *  - **Sin secreto configurado → se rechaza todo.** Un servidor sin secreto no
 *    tiene forma de distinguir al agente de un impostor: no procesa nada.
 *  - Falta de cabeceras, timestamp fuera de ventana o firma que no cuadra → 401.
 *  - Nunca se escribe nada ni se toca el cuerpo: solo deja pasar lo auténtico.
 */
class VerificaFirmaDelAgente
{
    public function handle(Request $request, Closure $next): Response
    {
        $secreto = (string) config('acceso.agente_secreto', '');

        if ($secreto === '') {
            Log::warning('Acceso: llegó una petición al endpoint del agente pero ACCESO_AGENTE_SECRETO no está configurado. Se rechaza.');

            return $this->rechazar('El endpoint de acceso no está configurado.');
        }

        $timestamp = $request->header('X-Agente-Timestamp');
        $firma     = $request->header('X-Agente-Firma');

        if (! is_string($timestamp) || ! is_string($firma) || $timestamp === '' || $firma === '') {
            return $this->rechazar('Petición sin firmar.');
        }

        // Anti-replay: el timestamp del agente (epoch) no puede estar lejos de ahora.
        if (! ctype_digit($timestamp)) {
            return $this->rechazar('Timestamp inválido.');
        }

        $ventana = (int) config('acceso.replay_ventana_segundos', 300);

        if (abs(time() - (int) $timestamp) > $ventana) {
            return $this->rechazar('Petición fuera de la ventana de tiempo permitida.');
        }

        // La firma cubre exactamente los bytes crudos del cuerpo, tal cual los
        // envió el agente: por eso se usa getContent() y no los datos parseados.
        $esperada = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $secreto);

        if (! hash_equals($esperada, $firma)) {
            return $this->rechazar('Firma inválida.');
        }

        return $next($request);
    }

    private function rechazar(string $mensaje): Response
    {
        return response()->json(['ok' => false, 'mensaje' => $mensaje], 401);
    }
}
