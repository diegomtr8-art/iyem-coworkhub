<?php

namespace App\Http\Controllers;

use App\Enums\BolsaDeHoras;
use App\Enums\EstadoRentaSalon;
use App\Models\Checkin;
use App\Models\Espacio;
use App\Models\Factura;
use App\Models\Plane;
use App\Models\RentaSalon;
use App\Models\Reserva;
use App\Models\Suscripcion;
use App\Models\User;
use App\Support\CeldaCsv;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    public function index(Request $request)
    {
        [$desde, $hasta] = $this->rango($request);

        return Inertia::render('Reportes/Index', [
            'rango' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'etiqueta' => $desde->translatedFormat('j M Y') . ' — ' . $hasta->translatedFormat('j M Y'),
                'dias'  => (int) $desde->diffInDays($hasta) + 1,
            ],

            'ocupacion'      => $this->ocupacionPorEspacio($desde, $hasta),
            'porFranja'      => $this->ocupacionPorFranja($desde, $hasta),
            'consumoPorPlan' => $this->consumoPorPlan($desde, $hasta),
            'ingresos'       => $this->ingresos($desde, $hasta),
            'noShow'         => $this->noShow($desde, $hasta),
            'enRiesgo'       => $this->miembrosEnRiesgo(),
        ]);
    }

    /**
     * Exportación a CSV.
     *
     * CSV y no Excel a propósito: se abre en Excel igual, no necesita una
     * biblioteca más y no se rompe cuando alguien lo sube a Google Sheets.
     * Lleva BOM porque sin él Excel en Windows destroza los acentos.
     */
    public function exportar(Request $request): StreamedResponse
    {
        [$desde, $hasta] = $this->rango($request);
        $informe = $request->string('informe')->toString() ?: 'ocupacion';

        $datos = match ($informe) {
            'consumo'   => $this->consumoPorPlan($desde, $hasta),
            'ingresos'  => $this->ingresos($desde, $hasta)['por_plan'],
            'no_show'   => $this->noShow($desde, $hasta)['por_miembro'],
            'en_riesgo' => $this->miembrosEnRiesgo(),
            default     => $this->ocupacionPorEspacio($desde, $hasta),
        };

        $nombre = "nodico-{$informe}-{$desde->toDateString()}-{$hasta->toDateString()}.csv";

        return response()->streamDownload(function () use ($datos) {
            $salida = fopen('php://output', 'w');

            // BOM de UTF-8: sin esto Excel en Windows enseña «Ocupacin».
            fwrite($salida, "\xEF\xBB\xBF");

            if (empty($datos)) {
                fputcsv($salida, ['Sin datos en el rango elegido']);
                fclose($salida);

                return;
            }

            fputcsv($salida, CeldaCsv::fila(array_keys((array) $datos[0])));

            // `CeldaCsv::fila` y no `fputcsv` a secas: varias columnas de estos
            // informes —nombre del miembro, telefono— las escribe el propio
            // miembro, y una celda que empieza por «=» la ejecuta Excel en la
            // maquina de quien abre el archivo. Ver `App\Support\CeldaCsv`.
            foreach ($datos as $fila) {
                fputcsv($salida, CeldaCsv::fila((array) $fila));
            }

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ── Informes ────────────────────────────────────────────────────────────

    private function ocupacionPorEspacio(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $reservas = Reserva::with('espacio:id,nombre,tipo')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereIn('estatus', ['Confirmada', 'Completada', 'No_Show'])
            ->get()
            ->groupBy('espacio_id');

        // Horas disponibles del periodo: días hábiles por la jornada.
        $habiles = $this->diasHabiles($desde, $hasta);
        $jornada = (\App\Models\Reserva::aMinutos(config('nodico.operacion.cierre'))
            - \App\Models\Reserva::aMinutos(config('nodico.operacion.apertura'))) / 60;
        $capacidad = round($habiles * $jornada, 1);

        return Espacio::reservables()->orderBy('nombre')->get()->map(function (Espacio $e) use ($reservas, $capacidad) {
            $delEspacio = $reservas->get($e->id, collect());
            $horas = round($delEspacio->sum(fn (Reserva $r) => $r->duracionEnHoras()), 1);

            return [
                'espacio'    => $e->nombre,
                'tipo'       => $e->tipo_label,
                'reservas'   => $delEspacio->count(),
                'horas'      => $horas,
                'capacidad_horas' => $capacidad,
                'ocupacion_pct'   => $capacidad > 0 ? (int) round($horas / $capacidad * 100) : 0,
            ];
        })->sortByDesc('ocupacion_pct')->values()->all();
    }

    /** Qué franjas se saturan: dice si hace falta abrir antes o cerrar después. */
    private function ocupacionPorFranja(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $reservas = Reserva::whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereIn('estatus', ['Confirmada', 'Completada', 'No_Show'])
            ->get(['hora_inicio', 'hora_fin']);

        $apertura = (int) substr(config('nodico.operacion.apertura'), 0, 2);
        $cierre   = (int) substr(config('nodico.operacion.cierre'), 0, 2);

        $franjas = [];

        for ($hora = $apertura; $hora < $cierre; $hora++) {
            $inicioMin = $hora * 60;
            $finMin    = $inicioMin + 60;

            $cuenta = $reservas->filter(function ($r) use ($inicioMin, $finMin) {
                $i = Reserva::aMinutos($r->hora_inicio);
                $f = Reserva::aMinutos($r->hora_fin);

                return $i < $finMin && $f > $inicioMin;
            })->count();

            $franjas[] = [
                'hora'     => sprintf('%02d:00', $hora),
                'reservas' => $cuenta,
            ];
        }

        $maximo = collect($franjas)->max('reservas') ?: 1;

        return array_map(
            fn (array $f) => [...$f, 'pct' => (int) round($f['reservas'] / $maximo * 100)],
            $franjas,
        );
    }

    /**
     * Horas consumidas contra incluidas, por plan.
     *
     * Es el informe que dice si los cupos están bien puestos: si el consumo
     * medio no llega ni a la mitad, el plan promete horas que nadie usa —y las
     * está pagando—; si roza el tope, se está quedando corto.
     */
    private function consumoPorPlan(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $filas = [];

        foreach (Plane::where('activo', true)->orderBy('orden')->get() as $plan) {
            $suscripciones = Suscripcion::where('plan_id', $plan->id)
                ->where(fn ($q) => $q->whereBetween('fecha_inicio', [$desde->toDateString(), $hasta->toDateString()])
                    ->orWhere(fn ($s) => $s->where('fecha_inicio', '<=', $hasta->toDateString())
                        ->where('fecha_fin', '>=', $desde->toDateString())))
                ->get();

            if ($suscripciones->isEmpty()) {
                continue;
            }

            foreach (BolsaDeHoras::cases() as $bolsa) {
                $cupo = $bolsa->cupo($plan);

                if ($cupo === null || $cupo <= 0) {
                    continue;
                }

                $consumos = DB::table('movimientos_horas')
                    ->whereIn('suscripcion_id', $suscripciones->pluck('id'))
                    ->where('bolsa', $bolsa->value)
                    ->whereBetween('created_at', [$desde->startOfDay(), $hasta->endOfDay()])
                    ->selectRaw('suscripcion_id, SUM(cantidad) as usado')
                    ->groupBy('suscripcion_id')
                    ->pluck('usado', 'suscripcion_id');

                $usadoTotal = round((float) $consumos->sum(), 1);
                $miembros   = $suscripciones->count();
                $promedio   = $miembros > 0 ? round($usadoTotal / $miembros, 1) : 0;

                $filas[] = [
                    'plan'          => $plan->nombre,
                    'bolsa'         => $bolsa->etiqueta(),
                    'miembros'      => $miembros,
                    'incluidas_c/u' => (float) $cupo,
                    'usadas_total'  => $usadoTotal,
                    'promedio_c/u'  => $promedio,
                    'aprovechamiento_pct' => (int) round($promedio / $cupo * 100),
                    // Quien llega al tope: los que se quedan cortos con su plan.
                    'al_tope'       => $consumos->filter(fn ($u) => (float) $u >= $cupo)->count(),
                ];
            }
        }

        return $filas;
    }

    private function ingresos(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $porPlan = Suscripcion::with('plan:id,nombre')
            ->whereBetween('fecha_inicio', [$desde->toDateString(), $hasta->toDateString()])
            ->get()
            ->groupBy(fn (Suscripcion $s) => $s->plan?->nombre ?? 'Sin plan')
            ->map(fn ($grupo, $nombre) => [
                'plan'         => $nombre,
                'membresias'   => $grupo->count(),
                'ingreso'      => round((float) $grupo->sum('precio_pagado'), 2),
            ])
            ->sortByDesc('ingreso')
            ->values()
            ->all();

        $salones = RentaSalon::whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereIn('estado', [EstadoRentaSalon::Confirmada->value, EstadoRentaSalon::Realizada->value])
            ->get();

        $facturado = Factura::where('estatus', 'Pagada')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->sum('total');

        return [
            'por_plan' => $porPlan,
            'salones'  => [
                'eventos' => $salones->count(),
                'ingreso' => round((float) $salones->sum('total'), 2),
                'cobrado' => round((float) $salones->sum('anticipo'), 2),
            ],
            'total_membresias' => round(collect($porPlan)->sum('ingreso'), 2),
            'facturado_pagado' => round((float) $facturado, 2),
        ];
    }

    private function noShow(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $reservas = Reserva::with('user:id,name')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->whereIn('estatus', ['Confirmada', 'Completada', 'No_Show'])
            ->get();

        $noShow = $reservas->where('estatus', 'No_Show');
        $total  = $reservas->count();

        return [
            'total_reservas' => $total,
            'no_show'        => $noShow->count(),
            'tasa_pct'       => $total > 0 ? round($noShow->count() / $total * 100, 1) : 0,
            'horas_perdidas' => round($noShow->sum(fn (Reserva $r) => $r->duracionEnHoras()), 1),

            'por_miembro' => $noShow
                ->groupBy('user_id')
                ->map(fn ($grupo) => [
                    'miembro' => $grupo->first()->user?->name ?? 'Sin nombre',
                    'faltas'  => $grupo->count(),
                    'horas'   => round($grupo->sum(fn (Reserva $r) => $r->duracionEnHoras()), 1),
                ])
                ->sortByDesc('faltas')
                ->take(15)
                ->values()
                ->all(),
        ];
    }

    /**
     * Miembros con membresía activa que llevan tiempo sin aparecer.
     *
     * El informe con más valor de todos: son los que están a punto de no
     * renovar y todavía se puede hacer algo. Un miembro que ya se fue solo sale
     * en el histórico.
     */
    private function miembrosEnRiesgo(): array
    {
        $hoy    = CarbonImmutable::today();
        $limite = $hoy->subDays(21);

        return User::miembros()
            ->whereHas('suscripciones', fn ($q) => $q->where('estatus', 'Activa')
                ->whereDate('fecha_fin', '>=', $hoy->toDateString()))
            ->with(['suscripcionActiva.plan:id,nombre'])
            ->withMax('checkins as ultimo_acceso', 'hora_entrada')
            ->get()
            ->filter(fn (User $u) => is_null($u->ultimo_acceso) || $u->ultimo_acceso < $limite)
            ->map(function (User $u) use ($hoy) {
                $ultimo = $u->ultimo_acceso ? CarbonImmutable::parse($u->ultimo_acceso) : null;

                return [
                    'miembro'  => $u->name,
                    'email'    => $u->email,
                    'telefono' => $u->telefono,
                    'plan'     => $u->suscripcionActiva?->plan?->nombre,
                    'vence'    => $u->suscripcionActiva?->fecha_fin?->toDateString(),
                    'ultimo_acceso' => $ultimo?->toDateString(),
                    'dias_sin_venir' => $ultimo ? (int) $ultimo->diffInDays($hoy) : null,
                    'url'      => route('miembros.show', $u),
                ];
            })
            ->sortByDesc(fn ($m) => $m['dias_sin_venir'] ?? 9999)
            ->values()
            ->all();
    }

    // ── Apoyo ───────────────────────────────────────────────────────────────

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function rango(Request $request): array
    {
        $desde = $request->filled('desde')
            ? CarbonImmutable::parse($request->string('desde')->toString())->startOfDay()
            : CarbonImmutable::today()->startOfMonth();

        $hasta = $request->filled('hasta')
            ? CarbonImmutable::parse($request->string('hasta')->toString())->startOfDay()
            : CarbonImmutable::today();

        return $desde->gt($hasta) ? [$hasta, $desde] : [$desde, $hasta];
    }

    private function diasHabiles(CarbonImmutable $desde, CarbonImmutable $hasta): int
    {
        $habiles = array_map('intval', config('nodico.operacion.dias_habiles', [1, 2, 3, 4, 5]));
        $cuenta  = 0;

        for ($dia = $desde; $dia->lte($hasta); $dia = $dia->addDay()) {
            if (in_array($dia->dayOfWeekIso, $habiles, true)) {
                $cuenta++;
            }
        }

        return $cuenta;
    }
}
