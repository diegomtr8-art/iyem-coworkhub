<?php

namespace App\Http\Controllers;

use App\Servicios\Reportes\GeneradorDeReportes;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes (Fase 3.10). Solo administración.
 *
 * Cada informe está aquí porque responde a una decisión concreta, no por
 * completar un tablero:
 *
 * - **Ocupación por espacio y franja**: qué salas se saturan y cuáles están
 *   muertas. Dice si sobra una sala o falta otra.
 * - **Horas consumidas contra incluidas**: si los cupos de los planes están
 *   bien calibrados. Si nadie llega ni a la mitad, el plan promete de más.
 * - **Ingresos por plan y por salón**.
 * - **Tasa de no-show**: cuánto se pierde en salas apartadas y vacías.
 * - **Miembros en riesgo**: los que dejaron de venir. Es el informe con más
 *   valor porque todavía se puede hacer algo con ellos.
 */
class ReportesController extends Controller
{
    public function __construct(private readonly GeneradorDeReportes $reportes)
    {
    }

    public function index(Request $request)
    {
        [$desde, $hasta] = $this->reportes->rango($request->input('desde'), $request->input('hasta'));

        return Inertia::render('Reportes/Index', [
            'rango'          => $this->reportes->describirRango($desde, $hasta),
            'ocupacion'      => $this->reportes->ocupacionPorEspacio($desde, $hasta),
            'porFranja'      => $this->reportes->ocupacionPorFranja($desde, $hasta),
            'consumoPorPlan' => $this->reportes->consumoPorPlan($desde, $hasta),
            'ingresos'       => $this->reportes->ingresos($desde, $hasta),
            'noShow'         => $this->reportes->noShow($desde, $hasta),
            'enRiesgo'       => $this->reportes->miembrosEnRiesgo(),
        ]);
    }

    /**
     * Exportación a CSV.
     *
     * CSV y no Excel a propósito: se abre en Excel igual, no necesita una
     * biblioteca más y no se rompe cuando alguien lo sube a Google Sheets.
     */
    public function exportar(Request $request): StreamedResponse
    {
        [$desde, $hasta] = $this->reportes->rango($request->input('desde'), $request->input('hasta'));
        $informe = $request->string('informe')->toString() ?: 'ocupacion';

        return $this->reportes->descargaCsv(
            $this->reportes->filasParaCsv($informe, $desde, $hasta),
            "nodico-{$informe}-{$desde->toDateString()}-{$hasta->toDateString()}.csv",
        );
    }
}
