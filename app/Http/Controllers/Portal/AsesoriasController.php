<?php

namespace App\Http\Controllers\Portal;

use App\Enums\BolsaDeHoras;
use App\Enums\CategoriaTema;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResuelveLaMembresia;
use App\Models\Asesor;
use App\Models\SolicitudAsesoria;
use App\Models\TemaAsesoria;
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

            // D.3 — la oferta: temas por categoría, cada uno con los asesores que
            // lo imparten. Solo si el plan incluye asesoría, para no consultar de
            // más cuando no aplica.
            'oferta' => $incluida ? $this->ofertaDeTemas() : [],

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
        // D.3 — el tema se elige del catálogo (`tema_id`). Se conserva también
        // el `detalle` libre opcional para matizar, y el asesor preferido.
        $datos = $request->validate([
            'tema_id'             => ['required', 'integer', 'exists:temas_asesoria,id'],
            'detalle'             => ['nullable', 'string', 'max:500'],
            'asesor_preferido_id' => ['nullable', 'integer', 'exists:asesores,id'],
            'dia_preferido'       => ['required', 'date', 'after_or_equal:today'],
            'horario_preferido'   => ['required', 'string', 'max:120'],
            'horas'               => ['nullable', 'numeric', 'min:0.5', 'max:8'],
        ], [
            'tema_id.required' => 'Elige un tema.',
        ]);

        $suscripcion = $this->membresiaVigente($request->user());

        if (! $suscripcion) {
            return back()->withErrors(['general' => 'Necesitas una membresía activa.']);
        }

        // El tema que se guarda es el nombre del catálogo, más el detalle si lo
        // hubo: así la bandeja lee una frase completa aunque el catálogo cambie.
        $tema = TemaAsesoria::findOrFail($datos['tema_id'])->nombre;
        if (! empty($datos['detalle'])) {
            $tema .= ' — ' . $datos['detalle'];
        }

        $this->gestor->solicitar(
            suscripcion: $suscripcion,
            tema: $tema,
            diaPreferido: $datos['dia_preferido'],
            horarioPreferido: $datos['horario_preferido'],
            horas: (float) ($datos['horas'] ?? 1),
            asesorPreferidoId: $datos['asesor_preferido_id'] ?? null,
        );

        return back()->with(
            'success',
            'Solicitud enviada. Recepción te confirmará día y asesor; te avisamos en cuanto esté.'
        );
    }

    /**
     * Temas activos agrupados por categoría, cada uno con los asesores que lo
     * imparten. Es lo que convierte la pantalla en una oferta que explorar.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ofertaDeTemas(): array
    {
        $temas = TemaAsesoria::activos()->with(['asesores' => fn ($q) => $q->where('activo', true)])->get();

        return collect(CategoriaTema::cases())->map(fn (CategoriaTema $cat) => [
            'categoria' => $cat->value,
            'etiqueta'  => $cat->etiqueta(),
            'temas'     => $temas->where('categoria', $cat->value)->values()->map(fn (TemaAsesoria $t) => [
                'id'                => $t->id,
                'nombre'            => $t->nombre,
                'descripcion_corta' => $t->descripcion_corta,
                'duracion_min'      => $t->duracion_min,
                'asesores'          => $t->asesores->map(fn (Asesor $a) => [
                    'id'     => $a->id,
                    'nombre' => $a->nombre,
                    'foto'   => $a->foto,
                ])->values(),
            ]),
        ])->filter(fn ($grupo) => $grupo['temas']->isNotEmpty())->values()->all();
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
