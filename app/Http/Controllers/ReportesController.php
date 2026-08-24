<?php

namespace App\Http\Controllers;

use App\Models\Espacio;
use App\Models\Factura;
use App\Models\Plane;
use App\Models\Reserva;
use App\Models\Suscripcion;
use Carbon\Carbon;
use Inertia\Inertia;

class ReportesController extends Controller
{
    public function index()
    {
        $ingresosPorMes = [];
        for ($i = 5; $i >= 0; $i--) {
            $mes = Carbon::now()->subMonths($i);
            $ingresosPorMes[] = [
                'mes'   => $mes->isoFormat('MMM YY'),
                'total' => Factura::where('estatus', 'Pagada')
                    ->whereYear('fecha', $mes->year)
                    ->whereMonth('fecha', $mes->month)
                    ->sum('total'),
            ];
        }

        $distribucionPlanes = Suscripcion::where('estatus', 'Activa')
            ->with('plan')
            ->get()
            ->groupBy('plan_id')
            ->map(fn($items) => [
                'plan'  => $items->first()->plan->nombre ?? 'N/A',
                'total' => $items->count(),
            ])
            ->values();

        $topEspacios = Espacio::withCount(['reservas' => fn($q) => $q->where('estatus', '!=', 'Cancelada')])
            ->orderByDesc('reservas_count')
            ->limit(5)
            ->get(['id', 'nombre', 'tipo', 'reservas_count']);

        return Inertia::render('Reportes/Index', [
            'ingresosPorMes'    => $ingresosPorMes,
            'distribucionPlanes'=> $distribucionPlanes,
            'topEspacios'       => $topEspacios,
        ]);
    }
}
