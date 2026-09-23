<?php

namespace App\Http\Controllers\Api\Movil;

use App\Enums\AccionOperativa;
use App\Models\EntradaBitacora;
use App\Servicios\Reportes\GeneradorDeReportes;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes de administración, solo lectura (docs/API-MOVIL.md §6.11).
 *
 * Mismos números que el panel web porque salen del mismo código
 * (`GeneradorDeReportes`). Los informes con datos de miembros —no-show y en
 * riesgo— dejan rastro en la bitácora, agrupado por hora.
 */
class ReportesController extends ControladorMovil
{
    private const DIAS_MAXIMOS = 366;

    public function __construct(private readonly GeneradorDeReportes $reportes)
    {
    }

    public function resumen(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rango($request);

        $ocupacion = collect($this->reportes->ocupacionPorEspacio($desde, $hasta));
        $ingresos  = $this->reportes->ingresos($desde, $hasta);
        $noShow    = $this->reportes->noShow($desde, $hasta);

        return $this->datos([
            'rango' => $this->reportes->describirRango($desde, $hasta),
            'ocupacion_media_pct' => $ocupacion->isEmpty() ? 0 : (int) round($ocupacion->avg('ocupacion_pct')),
            'reservas'            => (int) $ocupacion->sum('reservas'),
            'horas_reservadas'    => round((float) $ocupacion->sum('horas'), 1),
            // Total del periodo (membresías + salones), y su desglose.
            'ingresos'            => round($ingresos['total_membresias'] + $ingresos['salones']['ingreso'], 2),
            'ingresos_membresias' => $ingresos['total_membresias'],
            'facturado_pagado'    => $ingresos['facturado_pagado'],
            'ingresos_salones'    => $ingresos['salones']['ingreso'],
            'tasa_no_show_pct'    => $noShow['tasa_pct'],
            'miembros_en_riesgo'  => count($this->reportes->miembrosEnRiesgo()),
        ]);
    }

    public function ocupacion(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rango($request);

        return $this->datos([
            'rango'       => $this->reportes->describirRango($desde, $hasta),
            'por_espacio' => $this->reportes->ocupacionPorEspacio($desde, $hasta),
            'por_franja'  => $this->reportes->ocupacionPorFranja($desde, $hasta),
        ]);
    }

    public function consumo(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rango($request);

        // Las claves con «/» del panel no son cómodas en la app.
        $filas = collect($this->reportes->consumoPorPlan($desde, $hasta))->map(fn (array $f) => [
            'plan'                  => $f['plan'],
            'bolsa'                 => $f['bolsa'],
            'miembros'              => $f['miembros'],
            'incluidas_por_miembro' => $f['incluidas_c/u'],
            'usadas_total'          => $f['usadas_total'],
            'promedio_por_miembro'  => $f['promedio_c/u'],
            'aprovechamiento_pct'   => $f['aprovechamiento_pct'],
            'al_tope'               => $f['al_tope'],
        ])->values();

        return $this->datos(['rango' => $this->reportes->describirRango($desde, $hasta), 'filas' => $filas]);
    }

    public function ingresos(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rango($request);

        return $this->datos([
            'rango' => $this->reportes->describirRango($desde, $hasta),
            ...$this->reportes->ingresos($desde, $hasta),
        ]);
    }

    public function noShow(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rango($request);

        $this->registrarConsulta($request, 'no-show');

        return $this->datos([
            'rango' => $this->reportes->describirRango($desde, $hasta),
            ...$this->reportes->noShow($desde, $hasta),
        ]);
    }

    public function enRiesgo(Request $request): JsonResponse
    {
        $this->registrarConsulta($request, 'en-riesgo');

        // Sin el enlace al panel: en la app no lleva a ninguna parte.
        return $this->datos(collect($this->reportes->miembrosEnRiesgo())
            ->map(fn (array $m) => collect($m)->except('url')->all())
            ->values());
    }

    public function csv(Request $request, string $informe): StreamedResponse
    {
        [$desde, $hasta] = $this->rango($request);

        if (in_array($informe, ['no_show', 'en_riesgo'], true)) {
            $this->registrarConsulta($request, $informe . ' (csv)');
        }

        $filas = collect($this->reportes->filasParaCsv($informe, $desde, $hasta))
            ->map(fn ($f) => collect((array) $f)->except('url')->all())
            ->all();

        return $this->reportes->descargaCsv(
            $filas,
            "nodico-{$informe}-{$desde->toDateString()}-{$hasta->toDateString()}.csv",
        );
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function rango(Request $request): array
    {
        $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        [$desde, $hasta] = $this->reportes->rango($request->input('desde'), $request->input('hasta'));

        if ($desde->diffInDays($hasta) + 1 > self::DIAS_MAXIMOS) {
            throw ValidationException::withMessages(['desde' => 'El rango no puede pasar de un año.']);
        }

        return [$desde, $hasta];
    }

    /** Informes con datos de contacto de miembros: queda rastro, una vez por hora. */
    private function registrarConsulta(Request $request, string $informe): void
    {
        $usuario = $request->user();

        $reciente = EntradaBitacora::query()
            ->where('actor_user_id', $usuario->id)
            ->deAccion(AccionOperativa::ConsultaReportes)
            ->where('created_at', '>=', now()->subHour())
            ->where('descripcion', 'like', "%«{$informe}»%")
            ->exists();

        if ($reciente) {
            return;
        }

        EntradaBitacora::registrar(
            accion: AccionOperativa::ConsultaReportes,
            descripcion: "Consulta del informe «{$informe}» desde la app.",
            actor: $usuario,
            contexto: ['informe' => $informe, 'app' => true],
        );
    }
}
