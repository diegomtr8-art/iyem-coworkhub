<?php

namespace App\Http\Controllers;

use App\Enums\AccionOperativa;
use App\Enums\MotivoMovimiento;
use App\Models\BloqueoEspacio;
use App\Models\EntradaBitacora;
use App\Models\Espacio;
use App\Models\Reserva;
use App\Models\User;
use App\Servicios\Horas\LibroDeHoras;
use App\Servicios\Reservas\RegistroDeBloques;
use App\Servicios\Reservas\ReservaOperativa;
use App\Servicios\Reservas\ValidadorDeReserva;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Agenda semanal de espacios (Fase 3.4).
 *
 * Una columna por espacio y la semana entera a la vista. Es la pantalla que
 * responde «¿tienes algo libre el jueves?» sin abrir cinco días uno por uno.
 */
class AgendaController extends Controller
{
    public function __construct(
        private readonly ValidadorDeReserva $calendario,
        private readonly RegistroDeBloques $bloques,
        private readonly ReservaOperativa $reservas,
        private readonly LibroDeHoras $libro,
    ) {
    }

    public function index(Request $request)
    {
        $desde = $request->filled('semana')
            ? CarbonImmutable::parse($request->string('semana')->toString())->startOfWeek(CarbonImmutable::MONDAY)
            : CarbonImmutable::today()->startOfWeek(CarbonImmutable::MONDAY);

        $hasta = $desde->addDays(6);

        $espacios = Espacio::reservables()->orderBy('tipo')->orderBy('nombre')->get();

        $reservas = Reserva::with(['user:id,name', 'espacio:id,nombre'])
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->confirmadas()
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        $bloqueos = BloqueoEspacio::with('espacio:id,nombre')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->get();

        $dias = [];

        for ($dia = $desde; $dia->lte($hasta); $dia = $dia->addDay()) {
            $clave = $dia->toDateString();

            $dias[] = [
                'fecha'   => $clave,
                'nombre'  => $dia->translatedFormat('l'),
                'numero'  => $dia->day,
                'es_hoy'  => $dia->isToday(),
                'abierto' => $espacios->isNotEmpty() && $this->calendario->abreEse($espacios->first(), $dia),
                'columnas' => $espacios->map(function (Espacio $espacio) use ($reservas, $bloqueos, $clave, $dia) {
                    $franja = $this->calendario->franjaDelDia($espacio, $dia);

                    return [
                        'espacio_id' => $espacio->id,
                        'abierto'    => $franja !== null,
                        'apertura'   => $franja[0] ?? null,
                        'cierre'     => $franja[1] ?? null,
                        'eventos'    => $reservas
                            ->where('espacio_id', $espacio->id)
                            ->filter(fn (Reserva $r) => $r->fecha->toDateString() === $clave)
                            ->map(fn (Reserva $r) => [
                                'id'      => $r->id,
                                'tipo'    => 'reserva',
                                'titulo'  => $r->user?->name ?? 'Miembro',
                                'inicio'  => substr($r->hora_inicio, 0, 5),
                                'fin'     => substr($r->hora_fin, 0, 5),
                                'user_id' => $r->user_id,
                                'url'     => $r->user_id ? route('miembros.show', $r->user_id) : null,
                            ])
                            ->values()
                            ->concat($bloqueos
                                ->where('espacio_id', $espacio->id)
                                ->filter(fn (BloqueoEspacio $b) => $b->fecha->toDateString() === $clave)
                                ->map(fn (BloqueoEspacio $b) => [
                                    'id'      => $b->id,
                                    'tipo'    => 'bloqueo',
                                    'titulo'  => $b->motivo,
                                    'inicio'  => substr($b->hora_inicio, 0, 5),
                                    'fin'     => substr($b->hora_fin, 0, 5),
                                    'user_id' => null,
                                    'url'     => null,
                                ])
                                ->values())
                            ->sortBy('inicio')
                            ->values(),
                    ];
                })->values(),
            ];
        }

        return Inertia::render('Agenda/Index', [
            'semana' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'anterior' => $desde->subWeek()->toDateString(),
                'siguiente' => $desde->addWeek()->toDateString(),
                'etiqueta' => $desde->translatedFormat('j \d\e F') . ' — ' . $hasta->translatedFormat('j \d\e F \d\e Y'),
            ],
            'espacios' => $espacios->map(fn (Espacio $e) => [
                'id' => $e->id, 'nombre' => $e->nombre, 'tipo' => $e->tipo_label,
            ]),
            'dias'      => $dias,
            'operacion' => config('nodico.operacion'),
        ]);
    }

    /** Reserva desde el mostrador, con sobrecupo si se autoriza. */
    public function store(Request $request)
    {
        $datos = $request->validate([
            'user_id'     => ['required', 'exists:users,id'],
            'espacio_id'  => ['required', 'exists:espacios,id'],
            'fecha'       => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin'    => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'autoriza_sobrecupo' => ['boolean'],
            'motivo_sobrecupo'   => ['nullable', 'string', 'max:500'],
        ]);

        $resultado = $this->reservas->crear(
            miembro: User::findOrFail($datos['user_id']),
            espacio: Espacio::findOrFail($datos['espacio_id']),
            fecha: $datos['fecha'],
            horaInicio: $datos['hora_inicio'],
            horaFin: $datos['hora_fin'],
            operativo: $request->user(),
            autorizaSobrecupo: (bool) ($datos['autoriza_sobrecupo'] ?? false),
            motivoSobrecupo: $datos['motivo_sobrecupo'] ?? null,
        );

        return back()->with('success', $resultado['sobrecupo']
            ? 'Reserva creada con sobrecupo autorizado. Queda en la bitácora a tu nombre.'
            : 'Reserva creada.');
    }

    /** Mueve una reserva de horario o de espacio, respetando las mismas reglas. */
    public function mover(Request $request, Reserva $reserva)
    {
        $datos = $request->validate([
            'espacio_id'  => ['required', 'exists:espacios,id'],
            'fecha'       => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin'    => ['required', 'date_format:H:i', 'after:hora_inicio'],
        ]);

        $espacio = Espacio::findOrFail($datos['espacio_id']);

        $this->calendario->validar(
            espacio: $espacio,
            fecha: $datos['fecha'],
            horaInicio: $datos['hora_inicio'],
            horaFin: $datos['hora_fin'],
            comoOperativo: true,
        );

        DB::transaction(function () use ($reserva, $espacio, $datos, $request) {
            $antes = [
                'espacio' => $reserva->espacio?->nombre,
                'fecha'   => $reserva->fecha->toDateString(),
                'inicio'  => substr($reserva->hora_inicio, 0, 5),
                'fin'     => substr($reserva->hora_fin, 0, 5),
            ];

            $horasAntes = $reserva->duracionEnHoras();
            $horasNuevas = Reserva::calcularHoras($datos['hora_inicio'], $datos['hora_fin']);

            // Se libera primero y se vuelve a ocupar: si el destino choca, la
            // transacción revierte y el horario original se queda como estaba.
            $this->bloques->liberar($reserva);

            $reserva->update([
                'espacio_id'  => $espacio->id,
                'fecha'       => $datos['fecha'],
                'hora_inicio' => $datos['hora_inicio'],
                'hora_fin'    => $datos['hora_fin'],
            ]);

            try {
                $this->bloques->ocupar($reserva->refresh());
            } catch (QueryException) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'hora_inicio' => 'Ese horario ya está ocupado en ' . $espacio->nombre . '.',
                ]);
            }

            // Si cambió la duración, la bolsa tiene que reflejarlo.
            $diferencia = round($horasNuevas - $horasAntes, 2);

            if (abs($diferencia) >= 0.01 && $reserva->suscripcion && $reserva->bolsa()) {
                $this->libro->registrar(
                    suscripcion: $reserva->suscripcion,
                    bolsa: $reserva->bolsa(),
                    cantidad: $diferencia,
                    motivo: MotivoMovimiento::AjusteManual,
                    reserva: $reserva,
                    autor: $request->user(),
                    nota: 'Recepción movió la reserva y cambió su duración.',
                );
            }

            EntradaBitacora::registrar(
                accion: AccionOperativa::ReservaOperativa,
                descripcion: sprintf(
                    'Movió la reserva de %s: %s %s–%s → %s %s %s–%s.',
                    $reserva->user?->name ?? 'un miembro',
                    $antes['espacio'], $antes['inicio'], $antes['fin'],
                    $espacio->nombre, $datos['fecha'],
                    $datos['hora_inicio'], $datos['hora_fin'],
                ),
                actor: $request->user(),
                sujeto: $reserva->user,
                contexto: ['reserva_id' => $reserva->id, 'antes' => $antes],
            );
        });

        return back()->with('success', 'Reserva movida.');
    }

    public function cancelar(Request $request, Reserva $reserva)
    {
        $datos = $request->validate([
            'devolver_horas' => ['boolean'],
            'motivo'         => ['nullable', 'string', 'max:500'],
        ]);

        if (! $reserva->estaConfirmada()) {
            return back()->with('error', 'Esa reserva ya no estaba confirmada.');
        }

        DB::transaction(function () use ($reserva, $datos, $request) {
            $devolver = (bool) ($datos['devolver_horas'] ?? true);

            if ($devolver && $reserva->suscripcion && $reserva->bolsa()) {
                $this->libro->registrar(
                    suscripcion: $reserva->suscripcion,
                    bolsa: $reserva->bolsa(),
                    cantidad: -$reserva->duracionEnHoras(),
                    motivo: MotivoMovimiento::Cancelacion,
                    reserva: $reserva,
                    autor: $request->user(),
                    nota: 'Cancelada por recepción. ' . ($datos['motivo'] ?? ''),
                );
            }

            $reserva->update(['estatus' => 'Cancelada']);
            $this->bloques->liberar($reserva);

            EntradaBitacora::registrar(
                accion: AccionOperativa::CancelacionOperativa,
                descripcion: sprintf(
                    'Canceló la reserva de %s en %s del %s. %s',
                    $reserva->user?->name ?? 'un miembro',
                    $reserva->espacio?->nombre ?? '',
                    $reserva->fecha->toDateString(),
                    $devolver ? 'Se devolvieron las horas.' : 'NO se devolvieron las horas.',
                ),
                actor: $request->user(),
                sujeto: $reserva->user,
                motivo: $datos['motivo'] ?? null,
                contexto: ['reserva_id' => $reserva->id, 'devolvio' => $devolver],
            );
        });

        return back()->with('success', 'Reserva cancelada.');
    }

    // ── Bloqueos por mantenimiento o evento privado ─────────────────────────

    public function bloquear(Request $request)
    {
        $datos = $request->validate([
            'espacio_id'  => ['required', 'exists:espacios,id'],
            'fecha'       => ['required', 'date'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin'    => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'motivo'      => ['required', 'string', 'min:3', 'max:200'],
            'notas'       => ['nullable', 'string', 'max:500'],
        ], ['motivo.required' => 'Di por qué se bloquea: recepción va a tener que explicarlo.']);

        $espacio = Espacio::findOrFail($datos['espacio_id']);

        DB::transaction(function () use ($datos, $espacio, $request) {
            $bloqueo = BloqueoEspacio::create([
                ...$datos,
                'creado_por_user_id' => $request->user()->id,
            ]);

            try {
                $this->bloques->ocuparBloqueo($bloqueo);
            } catch (QueryException) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'hora_inicio' => 'Ese horario ya tiene una reserva o un bloqueo. '
                        . 'Cancélala primero si de verdad hay que cerrar la sala.',
                ]);
            }

            EntradaBitacora::registrar(
                accion: AccionOperativa::BloqueoEspacio,
                descripcion: sprintf(
                    'Bloqueó %s el %s de %s a %s: %s',
                    $espacio->nombre, $datos['fecha'],
                    $datos['hora_inicio'], $datos['hora_fin'], $datos['motivo'],
                ),
                actor: $request->user(),
                contexto: ['bloqueo_id' => $bloqueo->id],
            );
        });

        return back()->with('success', 'Espacio bloqueado.');
    }

    public function desbloquear(Request $request, BloqueoEspacio $bloqueo)
    {
        DB::transaction(function () use ($bloqueo, $request) {
            $this->bloques->liberarBloqueo($bloqueo);

            EntradaBitacora::registrar(
                accion: AccionOperativa::BloqueoEspacio,
                descripcion: 'Quitó el bloqueo de ' . ($bloqueo->espacio?->nombre ?? '') . ' del '
                    . $bloqueo->fecha->toDateString() . '.',
                actor: $request->user(),
                contexto: ['bloqueo_id' => $bloqueo->id],
            );

            $bloqueo->delete();
        });

        return back()->with('success', 'Bloqueo retirado.');
    }
}
