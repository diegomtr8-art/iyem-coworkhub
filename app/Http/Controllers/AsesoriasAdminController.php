<?php

namespace App\Http\Controllers;

use App\Enums\AccionOperativa;
use App\Enums\CategoriaTema;
use App\Enums\EstadoAsesoria;
use App\Models\Asesor;
use App\Models\EntradaBitacora;
use App\Models\SolicitudAsesoria;
use App\Models\TemaAsesoria;
use App\Servicios\Asesorias\GestorDeAsesorias;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Bandeja de solicitudes de asesoría (Fase 3.7).
 *
 * Lo que la pantalla tiene que dejar claro: **confirmar descuenta las horas y
 * rechazar no**. Es la diferencia entre las dos acciones y quien está en el
 * mostrador tiene que verla antes de pulsar, no después.
 */
class AsesoriasAdminController extends Controller
{
    public function __construct(private readonly GestorDeAsesorias $gestor)
    {
    }

    public function index(Request $request)
    {
        $estado = $request->string('estado')->toString() ?: 'pendientes';

        $solicitudes = SolicitudAsesoria::with([
                'user:id,name,email,telefono',
                // El plan se carga **entero**, no `plan:id,nombre`: los campos
                // de cupo (`horas_asesoria_mes`, `max_horas_asesoria_dia`)
                // hacen falta para calcular el saldo, y un `select` parcial los
                // deja en null, con lo que el saldo salia siempre en cero.
                'suscripcion.plan',
                'asesor:id,nombre',
                'asesorPreferido:id,nombre',
                'atendidaPor:id,name',
            ])
            ->when($estado === 'pendientes', fn ($q) => $q->pendientes())
            ->when($estado !== 'pendientes' && $estado !== 'todas', fn ($q) => $q->where('estado', $estado))
            ->orderByRaw("CASE WHEN estado = 'solicitada' THEN 0 ELSE 1 END")
            ->orderBy('dia_preferido')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (SolicitudAsesoria $s) => [
                'id'      => $s->id,
                'miembro' => [
                    'id'       => $s->user_id,
                    'nombre'   => $s->user?->name,
                    'email'    => $s->user?->email,
                    'telefono' => $s->user?->telefono,
                    'plan'     => $s->suscripcion?->plan?->nombre,
                    'url'      => $s->user_id ? route('miembros.show', $s->user_id) : null,
                ],
                'tema'              => $s->tema,
                'dia_preferido'     => $s->dia_preferido->toDateString(),
                'horario_preferido' => $s->horario_preferido,
                'horas'             => $s->horas,
                'estado'            => $s->estado->value,
                'estado_etiqueta'   => $s->estado->etiqueta(),
                'tono'              => $s->estado->tono(),
                'pendiente'         => $s->estado->estaPendiente(),
                'confirmada'        => $s->estado === EstadoAsesoria::Confirmada,
                'asesor'            => $s->asesorLegible(),
                'asesor_id'         => $s->asesor_id,
                // D.4 — el asesor que el miembro pidió, como sugerencia al confirmar.
                'asesor_sugerido'    => $s->asesorPreferido?->nombre,
                'asesor_sugerido_id' => $s->asesor_preferido_id,
                'fecha_confirmada'  => $s->fecha_confirmada?->toIso8601String(),
                'notas_operativo'   => $s->notas_operativo,
                'atendida_por'      => $s->atendidaPor?->name,
                'solicitada_el'     => $s->created_at->toIso8601String(),

                // El saldo del miembro, para no tener que abrir su ficha antes
                // de decidir si se le confirma.
                'saldo_asesoria'    => $s->suscripcion
                    ? \App\Enums\BolsaDeHoras::Asesoria->restante($s->suscripcion)
                    : null,
            ]);

        return Inertia::render('Asesorias/Index', [
            'solicitudes' => $solicitudes,
            'estado'      => $estado,
            'estados'     => collect(EstadoAsesoria::cases())
                ->map(fn ($e) => ['valor' => $e->value, 'etiqueta' => $e->etiqueta()]),
            'asesores'    => Asesor::activos()->get(['id', 'nombre', 'especialidad']),
            'pendientes'  => SolicitudAsesoria::pendientes()->count(),
            'cargaPorAsesor' => $this->cargaPorAsesor(),
        ]);
    }

    public function confirmar(Request $request, SolicitudAsesoria $asesoria)
    {
        $datos = $request->validate([
            'asesor_id'        => ['required', 'exists:asesores,id'],
            'fecha_confirmada' => ['required', 'date'],
            'notas'            => ['nullable', 'string', 'max:1000'],
        ], [
            'asesor_id.required' => 'Elige un asesor del catálogo.',
        ]);

        $this->gestor->confirmar(
            solicitud: $asesoria,
            operativo: $request->user(),
            fechaConfirmada: $datos['fecha_confirmada'],
            asesor: Asesor::findOrFail($datos['asesor_id']),
            notas: $datos['notas'] ?? null,
        );

        EntradaBitacora::registrar(
            accion: AccionOperativa::ConfirmacionAsesoria,
            descripcion: 'Confirmó la asesoría de ' . ($asesoria->user?->name ?? 'un miembro')
                . ' para el ' . CarbonImmutable::parse($datos['fecha_confirmada'])->translatedFormat('j \d\e F \a \l\a\s H:i') . '.',
            actor: $request->user(),
            sujeto: $asesoria->user,
            contexto: ['solicitud_id' => $asesoria->id, 'horas' => $asesoria->horas],
        );

        return back()->with('success', 'Asesoría confirmada y horas descontadas.');
    }

    public function rechazar(Request $request, SolicitudAsesoria $asesoria)
    {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'motivo.required' => 'Di por qué se rechaza: el miembro lo va a leer.',
        ]);

        $this->gestor->rechazar($asesoria, $request->user(), $datos['motivo']);

        EntradaBitacora::registrar(
            accion: AccionOperativa::RechazoAsesoria,
            descripcion: 'Rechazó la asesoría de ' . ($asesoria->user?->name ?? 'un miembro') . '.',
            actor: $request->user(),
            sujeto: $asesoria->user,
            motivo: $datos['motivo'],
            contexto: ['solicitud_id' => $asesoria->id],
        );

        return back()->with('success', 'Solicitud rechazada. No se descontó ninguna hora.');
    }

    public function realizada(Request $request, SolicitudAsesoria $asesoria)
    {
        $this->gestor->marcarRealizada($asesoria, $request->user());

        return back()->with('success', 'Asesoría marcada como realizada.');
    }

    // ── Catálogo de asesores ────────────────────────────────────────────────

    public function asesores()
    {
        return Inertia::render('Asesorias/Asesores', [
            'asesores' => Asesor::with('temas:id,nombre')->orderByDesc('activo')->orderBy('nombre')->get()
                ->map(fn (Asesor $a) => [
                    'id'             => $a->id,
                    'nombre'         => $a->nombre,
                    'foto'           => $a->foto,
                    'especialidad'   => $a->especialidad,
                    'semblanza'      => $a->semblanza,
                    'disponibilidad' => $a->disponibilidad,
                    'email'          => $a->email,
                    'telefono'       => $a->telefono,
                    'notas'          => $a->notas,
                    'activo'         => $a->activo,
                    'temas'          => $a->temas->pluck('id'),
                    'temas_nombres'  => $a->temas->pluck('nombre'),
                    'asesorias'      => $a->solicitudes()->count(),
                ]),

            // El catálogo de temas, para elegir las especialidades de cada asesor.
            'temasDisponibles' => TemaAsesoria::orderBy('nombre')->get(['id', 'nombre', 'categoria']),

            // D.4 — carga por asesor en los últimos 90 días.
            'carga' => $this->cargaPorAsesor(),
        ]);
    }

    public function guardarAsesor(Request $request, ?Asesor $asesor = null)
    {
        $datos = $request->validate([
            'nombre'         => ['required', 'string', 'max:150'],
            'foto'           => ['nullable', 'string', 'max:255'],
            'especialidad'   => ['nullable', 'string', 'max:150'],
            'semblanza'      => ['nullable', 'string', 'max:1000'],
            'disponibilidad' => ['nullable', 'string', 'max:255'],
            'email'          => ['nullable', 'email', 'max:150'],
            'telefono'       => ['nullable', 'string', 'max:30'],
            'notas'          => ['nullable', 'string', 'max:500'],
            'activo'         => ['boolean'],
            'temas'          => ['array'],
            'temas.*'        => ['integer', 'exists:temas_asesoria,id'],
        ]);

        $temas = $datos['temas'] ?? [];
        unset($datos['temas']);

        $modelo = $asesor?->exists ? tap($asesor)->update($datos) : Asesor::create($datos);
        $modelo->temas()->sync($temas);

        return back()->with('success', $asesor?->exists ? 'Asesor actualizado.' : 'Asesor dado de alta.');
    }

    // ── D.1 — catálogo de temas ──────────────────────────────────────────────

    public function temas()
    {
        return Inertia::render('Asesorias/Temas', [
            'temas' => TemaAsesoria::orderBy('orden')->orderBy('nombre')->get()
                ->map(fn (TemaAsesoria $t) => [
                    'id'                => $t->id,
                    'nombre'            => $t->nombre,
                    'descripcion_corta' => $t->descripcion_corta,
                    'categoria'         => $t->categoria,
                    'duracion_min'      => $t->duracion_min,
                    'activo'            => $t->activo,
                    'validado_iyem'     => $t->validado_iyem,
                    'orden'             => $t->orden,
                    'asesores'          => $t->asesores()->count(),
                ]),
            'categorias' => collect(CategoriaTema::cases())
                ->map(fn (CategoriaTema $c) => ['valor' => $c->value, 'etiqueta' => $c->etiqueta()]),
            // Aviso de que la oferta sembrada sigue pendiente de que el IYEM la valide.
            'hayProvisionales' => TemaAsesoria::where('validado_iyem', false)->exists(),
        ]);
    }

    public function guardarTema(Request $request, ?TemaAsesoria $tema = null)
    {
        $datos = $request->validate([
            'nombre'            => ['required', 'string', 'max:150'],
            'descripcion_corta' => ['nullable', 'string', 'max:255'],
            'categoria'         => ['required', Rule::in(CategoriaTema::valores())],
            'duracion_min'      => ['required', 'integer', 'min:15', 'max:480'],
            'activo'            => ['boolean'],
            'validado_iyem'     => ['boolean'],
            'orden'             => ['nullable', 'integer', 'min:0'],
        ]);

        $tema?->exists ? $tema->update($datos) : TemaAsesoria::create($datos);

        return back()->with('success', $tema?->exists ? 'Tema actualizado.' : 'Tema agregado.');
    }

    /**
     * Horas confirmadas por asesor en los últimos 90 días.
     *
     * Sin agendas por asesor, esto es lo único que dice si a alguien se le está
     * cargando la mano.
     */
    private function cargaPorAsesor(): array
    {
        $desde = CarbonImmutable::today()->subDays(90);

        return Asesor::activos()->get()->map(fn (Asesor $a) => [
            'id'     => $a->id,
            'nombre' => $a->nombre,
            'horas'  => $a->horasEntre($desde->toDateString(), CarbonImmutable::today()->toDateString()),
            'sesiones' => $a->solicitudes()
                ->whereIn('estado', [EstadoAsesoria::Confirmada->value, EstadoAsesoria::Realizada->value])
                ->whereDate('fecha_confirmada', '>=', $desde->toDateString())
                ->count(),
        ])->sortByDesc('horas')->values()->all();
    }
}
