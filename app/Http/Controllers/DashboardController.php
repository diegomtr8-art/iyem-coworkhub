<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\Factura;
use App\Models\Reserva;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $hoy = Carbon::today();
        $mesActual = Carbon::now()->startOfMonth();

        $miembrosActivos = User::miembros()
            ->whereHas('suscripciones', fn($q) => $q->where('estatus', 'Activa'))
            ->count();

        $reservasHoy = Reserva::whereDate('fecha', $hoy)->count();

        $checkinsActivos = Checkin::whereNull('hora_salida')->count();

        $ingresosMes = Factura::where('estatus', 'Pagada')
            ->where('fecha', '>=', $mesActual)
            ->sum('total');

        $totalEspacios = \App\Models\Espacio::count();
        $espaciosOcupados = Checkin::whereNull('hora_salida')
            ->distinct('espacio_id')->count('espacio_id');
        $ocupacionPct = $totalEspacios > 0 ? round(($espaciosOcupados / $totalEspacios) * 100) : 0;

        $facturasPendientes = Factura::where('estatus', 'Pendiente')->count();

        $reservasDeHoy = Reserva::with(['user', 'espacio'])
            ->whereDate('fecha', $hoy)
            ->orderBy('hora_inicio')
            ->get();

        $checkinsActuales = Checkin::with(['user', 'espacio'])
            ->whereNull('hora_salida')
            ->latest('hora_entrada')
            ->get();

        return Inertia::render('Dashboard', [
            'kpis' => [
                'miembros_activos'   => $miembrosActivos,
                'reservas_hoy'       => $reservasHoy,
                'checkins_activos'   => $checkinsActivos,
                'ingresos_mes'       => $ingresosMes,
                'ocupacion_pct'      => $ocupacionPct,
                'facturas_pendientes'=> $facturasPendientes,
            ],
            'reservasHoy'      => $reservasDeHoy,
            'checkinsActuales' => $checkinsActuales,
        ]);
    }
}
