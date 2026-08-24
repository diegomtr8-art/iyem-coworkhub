<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Espacio;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReservasController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $proximas = $user->reservas()
            ->with('espacio')
            ->where('fecha', '>=', today())
            ->where('estatus', 'Confirmada')
            ->orderBy('fecha')
            ->get();

        $pasadas = $user->reservas()
            ->with('espacio')
            ->where(fn($q) => $q->where('fecha', '<', today())
                ->orWhereIn('estatus', ['Cancelada', 'Completada', 'No_Show']))
            ->orderByDesc('fecha')
            ->limit(20)
            ->get();

        $suscripcion = $user->suscripciones()
            ->with('plan')
            ->where('estatus', 'Activa')
            ->latest()
            ->first();

        return Inertia::render('Portal/MisReservas', [
            'proximas'    => $proximas,
            'pasadas'     => $pasadas,
            'suscripcion' => $suscripcion,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $suscripcion = $user->suscripciones()
            ->with('plan')
            ->where('estatus', 'Activa')
            ->latest()
            ->first();

        $espaciosDisponibles = collect();
        if ($suscripcion) {
            $plan = $suscripcion->plan;
            $tipos = [];

            if ($plan?->incluye_sala_juntas) {
                $tipos[] = 'sala_juntas';
                $tipos[] = 'privado';
            }

            if ($plan?->horas_contenido_mes) {
                $tipos[] = 'contenido';
                $tipos[] = 'fotografia';
            }

            if ($plan?->esIlimitado() || $plan?->dias_cowork_mes) {
                $tipos[] = 'coworking';
            }

            $espaciosDisponibles = Espacio::where('disponible', true)
                ->whereIn('tipo', array_unique($tipos))
                ->get();
        }

        return Inertia::render('Portal/Reservar', [
            'espacios'    => $espaciosDisponibles,
            'suscripcion' => $suscripcion,
            'resumen'     => $suscripcion ? [
                'horas_sala_restantes'     => $suscripcion->horasSalaRestantes(),
                'horas_sala_max'           => $suscripcion->plan?->horas_sala_mes,
                'horas_contenido_restantes'=> $suscripcion->horasContenidoRestantes(),
                'horas_contenido_max'      => $suscripcion->plan?->horas_contenido_mes,
                'max_horas_sala_dia'       => $suscripcion->plan?->max_horas_sala_dia,
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'espacio_id'  => 'required|exists:espacios,id',
            'fecha'       => 'required|date|after_or_equal:today',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin'    => 'required|date_format:H:i|after:hora_inicio',
        ]);

        $user        = $request->user();
        $espacio     = Espacio::findOrFail($data['espacio_id']);
        $suscripcion = $user->suscripciones()->with('plan')->where('estatus', 'Activa')->latest()->first();

        if (!$suscripcion) {
            return back()->withErrors(['general' => 'No tienes una membresía activa.']);
        }

        // Validar que la fecha esté dentro del período de suscripción
        $fecha = Carbon::parse($data['fecha']);
        if ($fecha->gt(Carbon::parse($suscripcion->fecha_fin))) {
            return back()->withErrors(['fecha' => 'La fecha de reserva está fuera del período de tu membresía.']);
        }

        // Calcular duración correctamente con Carbon
        $horaInicio = Carbon::createFromFormat('H:i', $data['hora_inicio']);
        $horaFin    = Carbon::createFromFormat('H:i', $data['hora_fin']);
        $horas      = $horaFin->diffInMinutes($horaInicio) / 60;

        // Verificar disponibilidad del espacio (anti double-booking)
        $conflicto = Reserva::where('espacio_id', $data['espacio_id'])
            ->where('fecha', $data['fecha'])
            ->where('estatus', 'Confirmada')
            ->where('hora_inicio', '<', $data['hora_fin'])
            ->where('hora_fin', '>', $data['hora_inicio'])
            ->exists();

        if ($conflicto) {
            return back()->withErrors(['hora_fin' => 'Este espacio ya está reservado en ese horario. Elige otro horario.']);
        }

        return DB::transaction(function () use ($data, $user, $espacio, $suscripcion, $horas) {
            // Validar límite diario de sala
            if (in_array($espacio->tipo, ['privado', 'sala_juntas'])) {
                $maxDia = $suscripcion->plan?->max_horas_sala_dia;
                if ($maxDia !== null) {
                    $horasEseDia = Reserva::where('user_id', $user->id)
                        ->where('suscripcion_id', $suscripcion->id)
                        ->where('fecha', $data['fecha'])
                        ->whereHas('espacio', fn($q) => $q->whereIn('tipo', ['privado', 'sala_juntas']))
                        ->where('estatus', 'Confirmada')
                        ->get()
                        ->sum(function ($r) {
                            $i = Carbon::createFromFormat('H:i:s', $r->hora_inicio);
                            $f = Carbon::createFromFormat('H:i:s', $r->hora_fin);
                            return $f->diffInMinutes($i) / 60;
                        });

                    if ($horasEseDia + $horas > $maxDia) {
                        return back()->withErrors(['hora_fin' => "Máximo {$maxDia} hora(s) por día en salas privadas."]);
                    }
                }

                if ($suscripcion->horasSalaRestantes() !== null && $suscripcion->horasSalaRestantes() < $horas) {
                    return back()->withErrors(['hora_fin' => 'No tienes suficientes horas de sala disponibles este mes.']);
                }

                $suscripcion->increment('horas_sala_usadas', $horas);
            }

            if (in_array($espacio->tipo, ['contenido', 'fotografia'])) {
                if ($suscripcion->horasContenidoRestantes() !== null && $suscripcion->horasContenidoRestantes() < $horas) {
                    return back()->withErrors(['hora_fin' => 'No tienes suficientes horas de estudio disponibles este mes.']);
                }

                $suscripcion->increment('horas_contenido_usadas', $horas);
            }

            Reserva::create([
                ...$data,
                'user_id'        => $user->id,
                'suscripcion_id' => $suscripcion->id,
                'estatus'        => 'Confirmada',
                'precio_total'   => 0,
            ]);

            return redirect()->route('portal.reservas')->with('success', '¡Reserva confirmada! Te esperamos.');
        });
    }

    public function destroy(Reserva $reserva)
    {
        if ($reserva->user_id !== request()->user()->id) {
            abort(403);
        }

        DB::transaction(function () use ($reserva) {
            $suscripcion = $reserva->suscripcion;
            if ($suscripcion && $reserva->fecha >= today()) {
                $i    = Carbon::createFromFormat('H:i:s', $reserva->hora_inicio);
                $f    = Carbon::createFromFormat('H:i:s', $reserva->hora_fin);
                $horas = $f->diffInMinutes($i) / 60;

                if (in_array($reserva->espacio?->tipo, ['privado', 'sala_juntas'])) {
                    $suscripcion->decrement('horas_sala_usadas', max(0, $horas));
                }
                if (in_array($reserva->espacio?->tipo, ['contenido', 'fotografia'])) {
                    $suscripcion->decrement('horas_contenido_usadas', max(0, $horas));
                }
            }

            $reserva->update(['estatus' => 'Cancelada']);
        });

        return back()->with('success', 'Reserva cancelada. Las horas han sido devueltas a tu cuenta.');
    }
}
