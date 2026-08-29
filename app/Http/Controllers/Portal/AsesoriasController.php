<?php

namespace App\Http\Controllers\Portal;

use App\Enums\BolsaDeHoras;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResuelveLaMembresia;
use App\Models\SolicitudAsesoria;
use App\Servicios\Asesorias\GestorDeAsesorias;
use App\Servicios\Horas\LibroDeHoras;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Asesoría IYEM (Fase 2.7).
 *
 * Solo existe si el plan la incluye. Lo que la pantalla debe dejar claro es que
 * **esto es una solicitud, no una reserva**: el miembro dice qué día y en qué
 * franja le viene bien, y recepción asigna asesor y confirma. Llamarlo «reservar
 * asesoría» haría esperar una confirmación inmediata que no va a llegar.
 */
class AsesoriasController extends Controller
{
    use ResuelveLaMembresia;

    public function __construct(
        private readonly GestorDeAsesorias $gestor,
        private readonly LibroDeHoras $libro,
    ) {
    }

    public function index(Request $request)
    {
        $usuario     = $request->user();
        $suscripcion = $this->membresiaVigente($usuario);
        $incluida    = BolsaDeHoras::Asesoria->incluidaEn($suscripcion?->plan);

        return Inertia::render('Portal/Asesoria', [
            'incluida' => $incluida,

            'bolsa' => $incluida ? [
                'cupo'        => BolsaDeHoras::Asesoria->cupo($suscripcion->plan),
                'usado'       => $this->libro->consumoDelCiclo($suscripcion, BolsaDeHoras::Asesoria),
                'restante'    => $this->libro->saldoDelCiclo($suscripcion, BolsaDeHoras::Asesoria),
                'tope_diario' => BolsaDeHoras::Asesoria->topeDiario($suscripcion->plan),
                'reinicia_el' => $suscripcion->proximoReinicio()?->toDateString(),
            ] : null,

            'planQueLaIncluye' => $incluida ? null : \App\Models\Plane::query()
                ->where('activo', true)
                ->whereNotNull(BolsaDeHoras::Asesoria->campoCupo())
                ->orderBy('precio')
                ->first(['id', 'nombre', 'precio', 'periodo_label', 'stripe_url']),

            'vigenciaHasta' => $suscripcion?->fecha_fin->toDateString(),

            'solicitudes' => $usuario->asesorias()
                ->orderByDesc('created_at')
                ->limit(30)
                ->get()
                ->map(fn (SolicitudAsesoria $s) => [
                    'id'                => $s->id,
                    'tema'              => $s->tema,
                    'dia_preferido'     => $s->dia_preferido->toDateString(),
                    'horario_preferido' => $s->horario_preferido,
                    'horas'             => $s->horas,
                    'estado'            => $s->estado->value,
                    'estado_etiqueta'   => $s->estado->etiqueta(),
                    'tono'              => $s->estado->tono(),
                    'pendiente'         => $s->estado->estaPendiente(),
                    'final'             => $s->estado->esFinal(),
                    'asesor'            => $s->asesorLegible(),
                    'fecha_confirmada'  => $s->fecha_confirmada?->toIso8601String(),
                    'notas_operativo'   => $s->notas_operativo,
                    'solicitada_el'     => $s->created_at->toIso8601String(),
                ]),

            // Las franjas se ofrecen como opciones y no como hora exacta: sin
            // agenda de asesores, pedir «11:30» daría una precisión que el
            // sistema no puede cumplir.
            'franjas' => [
                'Por la mañana (9:00 a 12:00)',
                'A mediodía (12:00 a 15:00)',
                'Por la tarde (15:00 a 19:00)',
                'Me acomodo a lo que haya',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'tema'              => ['required', 'string', 'min:10', 'max:500'],
            'dia_preferido'     => ['required', 'date', 'after_or_equal:today'],
            'horario_preferido' => ['required', 'string', 'max:120'],
            'horas'             => ['nullable', 'numeric', 'min:0.5', 'max:8'],
        ], [
            'tema.required' => 'Cuéntanos de qué quieres hablar.',
            'tema.min'      => 'Da un poco más de detalle: ayuda a asignarte al asesor adecuado.',
        ]);

        $suscripcion = $this->membresiaVigente($request->user());

        if (! $suscripcion) {
            return back()->withErrors(['general' => 'Necesitas una membresía activa.']);
        }

        $this->gestor->solicitar(
            suscripcion: $suscripcion,
            tema: $datos['tema'],
            diaPreferido: $datos['dia_preferido'],
            horarioPreferido: $datos['horario_preferido'],
            horas: (float) ($datos['horas'] ?? 1),
        );

        return back()->with(
            'success',
            'Solicitud enviada. Recepción te confirmará día y asesor; te avisamos en cuanto esté.'
        );
    }

    public function destroy(Request $request, SolicitudAsesoria $asesoria)
    {
        if ($asesoria->user_id !== $request->user()->id) {
            abort(403);
        }

        $this->gestor->cancelar($asesoria, $request->user());

        return back()->with('success', 'Solicitud cancelada.');
    }
}
