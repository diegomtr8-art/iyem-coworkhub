<?php

namespace App\Http\Controllers;

use App\Models\VisitaInterior;
use App\Models\VisitanteInterior;
use App\Support\CeldaCsv;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fase 4.E — day-pass gratuito para el interior del estado.
 *
 * Recepción registra en el momento (sin solicitud previa). El valor es el dato
 * para el IYEM: cuántos vienen, de qué municipios y de qué giro. Decisión de
 * Nódico: **sin límite, solo registro** — no hay tope ni aviso, se cuenta.
 */
class DaypassInteriorController extends Controller
{
    /** Sección del panel: registro de mostrador, listado con filtros y resumen. */
    public function index(Request $request): Response
    {
        $municipio = $request->string('municipio')->toString();
        $giro      = $request->string('giro')->toString();
        $desde     = $request->date('desde');
        $hasta     = $request->date('hasta');

        $visitas = VisitaInterior::query()
            ->with('visitante')
            ->when($municipio, fn ($q) => $q->whereHas('visitante', fn ($v) => $v->where('municipio', $municipio)))
            ->when($giro, fn ($q) => $q->whereHas('visitante', fn ($v) => $v->where('giro', $giro)))
            ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('fecha')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (VisitaInterior $v) => [
                'id'        => $v->id,
                'fecha'     => $v->fecha->toDateString(),
                'nombre'    => $v->visitante?->nombre,
                'telefono'  => $v->visitante?->telefono,
                'municipio' => $v->visitante?->municipio,
                'giro'      => $v->visitante?->giro,
            ]);

        return Inertia::render('DaypassInterior/Index', [
            'visitas'    => $visitas,
            'filtros'    => ['municipio' => $municipio, 'giro' => $giro,
                             'desde' => $desde?->toDateString(), 'hasta' => $hasta?->toDateString()],
            'municipios' => VisitanteInterior::query()->select('municipio')->distinct()->orderBy('municipio')->pluck('municipio'),
            'giros'      => VisitanteInterior::query()->whereNotNull('giro')->select('giro')->distinct()->orderBy('giro')->pluck('giro'),
            'resumen'    => $this->resumenDelMes(),
        ]);
    }

    /** Mostrador: buscar un visitante por nombre o teléfono para no recapturar. */
    public function buscar(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->toString());

        $resultados = strlen($q) < 2 ? collect() : VisitanteInterior::query()
            ->where('nombre', 'like', "%{$q}%")
            ->orWhere('telefono', 'like', "%{$q}%")
            ->orderBy('nombre')
            ->limit(8)
            ->get(['id', 'nombre', 'telefono', 'municipio', 'giro', 'como_se_entero']);

        return response()->json(['resultados' => $resultados]);
    }

    /**
     * Alta rápida de mostrador. Si `visitante_id` viene, es alguien que ya vino
     * y solo se registra la visita de hoy; si no, se crea la ficha y la visita.
     */
    public function registrar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'visitante_id'    => ['nullable', 'integer', 'exists:visitantes_interior,id'],
            'nombre'          => ['required_without:visitante_id', 'nullable', 'string', 'max:150'],
            'telefono'        => ['nullable', 'string', 'max:30'],
            'municipio'       => ['required_without:visitante_id', 'nullable', 'string', 'max:120'],
            'giro'            => ['nullable', 'string', 'max:120'],
            'como_se_entero'  => ['nullable', 'string', 'max:150'],
        ]);

        DB::transaction(function () use ($datos, $request) {
            $visitante = ! empty($datos['visitante_id'])
                ? VisitanteInterior::findOrFail($datos['visitante_id'])
                : VisitanteInterior::create([
                    'nombre'         => $datos['nombre'],
                    'telefono'       => $datos['telefono'] ?? null,
                    'municipio'      => $datos['municipio'],
                    'giro'           => $datos['giro'] ?? null,
                    'como_se_entero' => $datos['como_se_entero'] ?? null,
                ]);

            $visitante->visitas()->create([
                'fecha'                  => CarbonImmutable::today()->toDateString(),
                'registrado_por_user_id' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Day-pass registrado. Ya aparece en el tablero de hoy.');
    }

    /** Exportación a Excel (CSV) del listado filtrado, para el reporte al IYEM. */
    public function exportar(Request $request): StreamedResponse
    {
        $municipio = $request->string('municipio')->toString();
        $giro      = $request->string('giro')->toString();
        $desde     = $request->date('desde');
        $hasta     = $request->date('hasta');

        $visitas = VisitaInterior::query()->with('visitante')
            ->when($municipio, fn ($q) => $q->whereHas('visitante', fn ($v) => $v->where('municipio', $municipio)))
            ->when($giro, fn ($q) => $q->whereHas('visitante', fn ($v) => $v->where('giro', $giro)))
            ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('fecha')
            ->get();

        $nombre = 'daypass-interior-' . CarbonImmutable::now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($visitas) {
            $salida = fopen('php://output', 'w');
            fprintf($salida, "\xEF\xBB\xBF"); // BOM para que Excel lea los acentos
            fputcsv($salida, ['Fecha', 'Nombre', 'Teléfono', 'Municipio', 'Giro', 'Cómo se enteró']);
            foreach ($visitas as $v) {
                fputcsv($salida, CeldaCsv::fila([
                    $v->fecha->toDateString(),
                    $v->visitante?->nombre,
                    $v->visitante?->telefono,
                    $v->visitante?->municipio,
                    $v->visitante?->giro,
                    $v->visitante?->como_se_entero,
                ]));
            }
            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> — el dato que sostiene el programa ante el IYEM. */
    private function resumenDelMes(): array
    {
        $inicio = CarbonImmutable::today()->startOfMonth()->toDateString();

        $delMes = VisitaInterior::with('visitante')->whereDate('fecha', '>=', $inicio)->get();

        return [
            'total_mes'      => $delMes->count(),
            'por_municipio'  => $delMes->groupBy(fn ($v) => $v->visitante?->municipio ?? '—')
                ->map->count()->sortDesc(),
            'por_giro'       => $delMes->groupBy(fn ($v) => $v->visitante?->giro ?? '—')
                ->map->count()->sortDesc(),
        ];
    }
}
