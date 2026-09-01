<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Servicios\Accesos\IngestaDeEventos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints servicio-a-servicio del agente de acceso (Fase 2).
 *
 * No usan sesión web ni CSRF: su autenticidad la da la firma HMAC del agente,
 * verificada por `VerificaFirmaDelAgente` antes de llegar aquí. El agente
 * considera éxito **solo HTTP 200**.
 */
class AccesoController extends Controller
{
    /** Clave del último contacto del agente, para el estado del sistema (Fase 5). */
    public const AGENTE_VISTO = 'acceso.agente_visto_en';

    public function __construct(private readonly IngestaDeEventos $ingesta)
    {
    }

    /** Cualquier contacto del agente (eventos, alerta o latido) actualiza su «visto». */
    private function marcarVisto(): void
    {
        Cache::put(self::AGENTE_VISTO, now()->toIso8601String(), now()->addDay());
    }

    /** Latido: el agente lo manda aunque no haya eventos, para que Nódico sepa que vive. */
    public function latido(Request $request): JsonResponse
    {
        $this->marcarVisto();

        return response()->json(['ok' => true]);
    }

    /**
     * Recibe un lote de eventos del terminal facial. Cada evento se procesa de
     * forma idempotente por `origen_id`.
     */
    public function eventos(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'eventos'                 => ['required', 'array', 'min:1'],
            'eventos.*.origen_id'     => ['required', 'integer'],
            'eventos.*.ocurrido_en'   => ['required', 'string'],
            'eventos.*.person_id'     => ['required', 'integer'],
            'eventos.*.person_type'   => ['required', 'integer'],
            'eventos.*.reconocido'    => ['required', 'boolean'],
            'eventos.*.pass_type'     => ['present', 'nullable', 'string'],
            'eventos.*.sub_pass_type' => ['nullable', 'string'],
            'eventos.*.direction'     => ['required', 'integer'],
            'eventos.*.device_id'     => ['nullable', 'integer'],
            'eventos.*.device_key'    => ['nullable', 'string'],
        ]);

        $resumen = [];

        foreach ($datos['eventos'] as $evento) {
            $resultado = $this->ingesta->procesar($evento);
            $resumen[$resultado] = ($resumen[$resultado] ?? 0) + 1;
        }

        $this->marcarVisto();

        return response()->json([
            'ok'         => true,
            'recibidos'  => count($datos['eventos']),
            'resultados' => $resumen,
        ]);
    }

    /**
     * Recibe una alerta del agente (p. ej. «id retrocedido»). Deja constancia y
     * responde 200 para que el agente no la reintente en bucle.
     */
    public function alerta(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo'    => ['required', 'string', 'max:100'],
            'detalle' => ['required', 'string', 'max:2000'],
            'cuando'  => ['nullable', 'string', 'max:60'],
        ]);

        $this->marcarVisto();

        // El agente se detiene solo ante un id retrocedido; esto es la campana que
        // avisa al operativo de que Smart Pass necesita intervención humana.
        Log::channel(config('logging.default'))->warning('Acceso: alerta del agente.', [
            'tipo'    => $datos['tipo'],
            'detalle' => $datos['detalle'],
            'cuando'  => $datos['cuando'] ?? now()->toIso8601String(),
        ]);

        return response()->json(['ok' => true]);
    }
}
