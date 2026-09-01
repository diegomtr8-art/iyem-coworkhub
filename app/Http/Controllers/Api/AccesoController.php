<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComandoAcceso;
use App\Servicios\Accesos\IngestaDeEventos;
use App\Servicios\Accesos\VinculadorDeRostros;
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

    public function __construct(
        private readonly IngestaDeEventos $ingesta,
        private readonly VinculadorDeRostros $vinculador,
    ) {
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
     * El agente pregunta por órdenes pendientes (abrir puerta, etc.). Se las
     * lleva y quedan marcadas como «enviadas»: el agente reporta el resultado
     * aparte. Es la mitad saliente que permite mandar sin abrir puertos.
     */
    public function comandos(Request $request): JsonResponse
    {
        $this->marcarVisto();

        $pendientes = ComandoAcceso::where('estado', 'pendiente')
            ->orderBy('id')->limit(20)->get();

        if ($pendientes->isNotEmpty()) {
            ComandoAcceso::whereIn('id', $pendientes->pluck('id'))
                ->update(['estado' => 'enviado', 'enviado_en' => now()]);
        }

        return response()->json([
            'ok'       => true,
            'comandos' => $pendientes->map(fn (ComandoAcceso $c) => [
                'id'        => $c->id,
                'tipo'      => $c->tipo,
                'device_id' => $c->device_id,
                'person_id' => $c->person_id,
                'payload'   => $c->payload,   // datos de enrolado (nombre, foto, etc.)
            ])->values(),
        ]);
    }

    /**
     * El agente reporta si una orden se ejecutó o falló.
     *
     * El grupo de rutas del agente no monta `SubstituteBindings` (es servicio a
     * servicio, sin el stack web), así que el id llega como string y se resuelve
     * a mano en vez de por binding implícito.
     */
    public function resultadoComando(Request $request, string $comando): JsonResponse
    {
        $datos = $request->validate([
            'ok'        => ['required', 'boolean'],
            'detalle'   => ['nullable', 'string', 'max:2000'],
            'person_id' => ['nullable', 'integer'],   // lo devuelve el enrolado
        ]);

        $this->marcarVisto();

        $orden = ComandoAcceso::findOrFail((int) $comando);
        $orden->update([
            'estado'      => $datos['ok'] ? 'ejecutado' : 'fallido',
            'resultado'   => $datos['detalle'] ?? null,
            'person_id'   => $datos['person_id'] ?? $orden->person_id,
            'resuelto_en' => now(),
        ]);

        // Si fue un enrolado que salió bien, amarra el rostro recién creado al
        // perfil de Nódico que lo pidió (miembro o ficha de persona).
        if ($datos['ok'] && $orden->tipo === ComandoAcceso::ENROLAR_ROSTRO && ! empty($datos['person_id'])) {
            $this->vinculador->vincularDesdeEnrolado($orden, (int) $datos['person_id']);
        }

        return response()->json(['ok' => true]);
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
