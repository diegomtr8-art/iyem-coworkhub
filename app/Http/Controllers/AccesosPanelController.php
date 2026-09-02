<?php

namespace App\Http\Controllers;

use App\Enums\AccionOperativa;
use App\Http\Controllers\Api\AccesoController;
use App\Models\Checkin;
use App\Models\ComandoAcceso;
use App\Models\EntradaBitacora;
use App\Models\EventoAcceso;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La pantalla de accesos del panel operativo (Fase 5).
 *
 * Sustituye a Smart Pass para el día a día: quién está dentro, el registro
 * completo, los no reconocidos para revisar, y —muy importante— el **estado del
 * sistema**, porque un tablero que muestra cero accesos porque el agente está
 * caído es peor que uno que avisa que está caído.
 */
class AccesosPanelController extends Controller
{
    /** Minutos sin latido del agente tras los que se le da por caído. */
    private const AGENTE_TIMEOUT_MIN = 5;

    public function index(Request $request): Response
    {
        $filtros = $request->only(['buscar', 'tipo', 'desde', 'hasta', 'device']);

        $registro = EventoAcceso::query()
            ->with('user:id,name')
            ->when($filtros['tipo'] ?? null, fn ($q, $t) => match ($t) {
                'reconocido' => $q->where('reconocido', true),
                'extrano'    => $q->where('reconocido', false),
                default      => $q,
            })
            ->when($filtros['device'] ?? null, fn ($q, $d) => $q->where('device_key', $d))
            ->when($filtros['desde'] ?? null, fn ($q, $d) => $q->whereDate('ocurrido_en', '>=', $d))
            ->when($filtros['hasta'] ?? null, fn ($q, $h) => $q->whereDate('ocurrido_en', '<=', $h))
            ->when($filtros['buscar'] ?? null, fn ($q, $b) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$b}%")))
            ->orderByDesc('ocurrido_en')
            ->paginate(30)->withQueryString()
            ->through(fn (EventoAcceso $e) => $this->paraLaVista($e));

        return Inertia::render('Accesos/Index', [
            'dentroAhora' => Checkin::whereNull('hora_salida')
                ->with('user:id,name', 'espacio:id,nombre')
                ->orderByDesc('hora_entrada')->get()
                ->map(fn (Checkin $c) => [
                    'id' => $c->id, 'miembro' => $c->user?->name,
                    'espacio' => $c->espacio?->nombre ?? 'Coworking',
                    'desde' => $c->hora_entrada?->toIso8601String(),
                ]),

            'registro' => $registro,
            'filtros'  => $filtros,

            // Los no reconocidos con más de X, para su bandeja de revisión.
            'noReconocidos' => EventoAcceso::where('reconocido', false)
                ->orderByDesc('ocurrido_en')->limit(50)->get()
                ->map(fn (EventoAcceso $e) => $this->paraLaVista($e)),

            // Reconocidos que llegaron SIN amarre: candidatos a vincular (Fase 3).
            'sinVincular' => EventoAcceso::where('reconocido', true)->whereNull('user_id')
                ->select('person_id')->groupBy('person_id')->orderByDesc('person_id')
                ->pluck('person_id'),

            'estadoAgente' => $this->estadoAgente(),
            'estadoTorno'  => $this->estadoTorno(),

            // Dispositivos vistos, para elegir cuál abrir.
            'dispositivos' => EventoAcceso::query()
                ->select('device_id', 'device_key')
                ->whereNotNull('device_id')
                ->groupBy('device_id', 'device_key')
                ->orderBy('device_key')->get()
                ->map(fn (EventoAcceso $e) => ['id' => $e->device_id, 'clave' => $e->device_key]),

            // Últimas órdenes de puerta, con su estado.
            'comandos' => ComandoAcceso::with('solicitante:id,name')
                ->orderByDesc('id')->limit(8)->get()
                ->map(fn (ComandoAcceso $c) => [
                    'id'          => $c->id,
                    'tipo'        => $c->tipo,
                    'device_id'   => $c->device_id,
                    'estado'      => $c->estado,
                    'resultado'   => $c->resultado,
                    'por'         => $c->solicitante?->name,
                    'creado_en'   => $c->created_at?->toIso8601String(),
                    'resuelto_en' => $c->resuelto_en?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Fase 4 — abre la puerta desde el panel. No manda directo (Nódico no alcanza
     * el localhost de la oficina): **encola** la orden y el agente la ejecuta en su
     * siguiente sondeo. La pantalla muestra cómo va quedando.
     */
    public function abrirPuerta(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'device_id' => ['nullable', 'integer'],
        ]);

        $comando = ComandoAcceso::create([
            'tipo'                    => ComandoAcceso::ABRIR_PUERTA,
            'device_id'               => $datos['device_id'] ?? null,
            'estado'                  => 'pendiente',
            'solicitado_por_user_id'  => $request->user()->id,
        ]);

        EntradaBitacora::registrar(
            accion: AccionOperativa::AperturaPuerta,
            descripcion: 'Encoló abrir la puerta' . ($comando->device_id ? " (dispositivo {$comando->device_id})" : '') . '.',
            actor: $request->user(),
            contexto: ['comando_id' => $comando->id, 'device_id' => $comando->device_id],
        );

        return back()->with('success', 'Orden enviada. El agente abrirá la puerta en unos segundos.');
    }

    /**
     * Fase 3 (camino de vincular) — amarra un `person_id` de Smart Pass a un
     * miembro. No necesita la API en vivo: usa el id que ya viene en los eventos.
     */
    public function vincular(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'person_id' => ['required', 'integer'],
            'email'     => ['required', 'email'],
        ]);

        $miembro = User::whereRaw('LOWER(email) = ?', [mb_strtolower($datos['email'])])->first();
        if (! $miembro) {
            return back()->with('error', 'No hay ninguna cuenta con ese correo.');
        }

        $ocupado = User::where('smartpass_person_id', $datos['person_id'])->whereKeyNot($miembro->id)->exists();
        if ($ocupado) {
            return back()->with('error', 'Ese rostro ya está vinculado a otro miembro.');
        }

        // smartpass_person_id no es asignable en masa (nota A.4 de seguridad).
        $miembro->forceFill(['smartpass_person_id' => $datos['person_id']])->save();

        // Los eventos previos de ese rostro que quedaron sin dueño pasan al miembro
        // (para su historial); no se recalculan check-ins retroactivos.
        EventoAcceso::where('person_id', $datos['person_id'])->whereNull('user_id')
            ->update(['user_id' => $miembro->id]);

        EntradaBitacora::registrar(
            accion: AccionOperativa::VinculacionRostro,
            descripcion: "Vinculó el rostro (persona {$datos['person_id']} de Smart Pass) a {$miembro->name}.",
            actor: $request->user(),
            sujeto: $miembro,
            contexto: ['smartpass_person_id' => $datos['person_id']],
        );

        return back()->with('success', "Listo: {$miembro->name} quedó vinculado al rostro {$datos['person_id']}.");
    }

    public function exportar(Request $request): StreamedResponse
    {
        $eventos = EventoAcceso::query()->with('user:id,name')
            ->when($request->input('desde'), fn ($q, $d) => $q->whereDate('ocurrido_en', '>=', $d))
            ->when($request->input('hasta'), fn ($q, $h) => $q->whereDate('ocurrido_en', '<=', $h))
            ->orderByDesc('ocurrido_en')->get();

        return response()->streamDownload(function () use ($eventos) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Fecha y hora', 'Tipo', 'Miembro', 'Persona (Smart Pass)', 'Dispositivo', 'Direccion']);
            foreach ($eventos as $e) {
                fputcsv($out, [
                    $e->ocurrido_en?->toDateTimeString(),
                    $e->reconocido ? 'Reconocido' : 'Extrano',
                    $e->user?->name ?? '',
                    $e->person_id,
                    $e->device_key,
                    $e->direction,
                ]);
            }
            fclose($out);
        }, 'accesos-nodico-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ── Interno ──────────────────────────────────────────────────────────────

    private function estadoAgente(): array
    {
        $visto = Cache::get(AccesoController::AGENTE_VISTO);
        $vistoEn = $visto ? CarbonImmutable::parse($visto) : null;
        $minutos = $vistoEn ? (int) $vistoEn->diffInMinutes(now()) : null;

        return [
            'visto_en'  => $vistoEn?->toIso8601String(),
            'minutos'   => $minutos,
            'sano'      => $vistoEn !== null && $minutos < self::AGENTE_TIMEOUT_MIN,
            'nunca'     => $vistoEn === null,
        ];
    }

    /**
     * Estado del torno FR07, tal como lo reportó el agente en su último latido.
     * Si el agente está caído, el dato es viejo: se marca «desconocido» para no
     * mostrar un «en línea» que en realidad nadie confirmó hace rato.
     */
    private function estadoTorno(): array
    {
        $t = Cache::get(AccesoController::TORNO_ESTADO);
        if (! is_array($t)) {
            return ['conocido' => false, 'online' => false, 'antiguedad_seg' => null, 'ultimo' => null, 'reportado_en' => null, 'fresco' => false];
        }

        // El reporte es «fresco» si llegó hace menos que el timeout del agente:
        // pasado eso, el agente probablemente no está reportando y no sabemos nada.
        $reportado = $t['reportado_en'] ? CarbonImmutable::parse($t['reportado_en']) : null;
        $fresco = $reportado !== null && $reportado->diffInMinutes(now()) < self::AGENTE_TIMEOUT_MIN;

        $recon = Cache::get(AccesoController::TORNO_RECONEXION);
        $recon = is_array($recon) ? $recon : [];

        return [
            'conocido'         => $fresco,
            'online'           => $fresco && (bool) ($t['online'] ?? false),
            'antiguedad_seg'   => $t['antiguedad_seg'] ?? null,
            'ultimo'           => $t['ultimo'] ?? null,
            'reportado_en'     => $t['reportado_en'] ?? null,
            'fresco'           => $fresco,
            // Resumen del día del motor de reconexión (Fase 4): el número que
            // convierte «a veces se cae» en «ayer se cayó 14 veces, 40 min sin registrar».
            'caidas_hoy'       => (int) ($recon['caidas_hoy'] ?? 0),
            'reconexiones_hoy' => (int) ($recon['reconexiones_hoy'] ?? 0),
            'downtime_seg_hoy' => (int) ($recon['downtime_seg_hoy'] ?? 0),
            'cap_alcanzado'    => (bool) ($recon['cap_alcanzado'] ?? false),
        ];
    }

    /**
     * Fase 2 de la reconexión — encola el «Grabar» (reescribir la contraseña LAN)
     * para que el torno se re-registre. No va directo: Nódico no alcanza el
     * localhost de la oficina; el agente lo ejecuta en su siguiente sondeo.
     */
    public function reconectar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'device_id' => ['nullable', 'integer'],
        ]);

        ComandoAcceso::create([
            'tipo'                    => ComandoAcceso::RECONECTAR_TORNO,
            'device_id'               => $datos['device_id'] ?? 1,
            'estado'                  => 'pendiente',
            'solicitado_por_user_id'  => $request->user()->id,
        ]);

        EntradaBitacora::registrar(
            accion: AccionOperativa::ReconexionTorno,
            descripcion: 'Reconexión manual del torno solicitada desde el panel.',
            actor: $request->user(),
            contexto: ['device_id' => $datos['device_id'] ?? 1],
        );

        return back()->with('success', 'Reconectando el torno… en unos segundos verás el resultado.');
    }

    private function paraLaVista(EventoAcceso $e): array
    {
        return [
            'id'          => $e->id,
            'ocurrido_en' => $e->ocurrido_en?->toIso8601String(),
            'reconocido'  => (bool) $e->reconocido,
            'miembro'     => $e->user?->name,
            'person_id'   => $e->person_id,
            'device_key'  => $e->device_key,
            'direction'   => $e->direction,
            'genero_checkin' => (bool) $e->genero_checkin,
        ];
    }
}
