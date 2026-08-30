<?php

namespace App\Http\Controllers;

use App\Enums\AccionOperativa;
use App\Enums\EstadoAsesoria;
use App\Models\Asesor;
use App\Models\EntradaBitacora;
use App\Models\SolicitudAsesoria;
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
            'asesores' => Asesor::orderByDesc('activo')->orderBy('nombre')->get()
                ->map(fn (Asesor $a) => [
                    'id'           => $a->id,
                    'nombre'       => $a->nombre,
                    'especialidad' => $a->especialidad,
                    'email'        => $a->email,
                    'telefono'     => $a->telefono,
                    'notas'        => $a->notas,
                    'activo'       => $a->activo,
                    'asesorias'    => $a->solicitudes()->count(),
                ]),
        ]);
    }

    public function guardarAsesor(Request $request, ?Asesor $asesor = null)
    {
        $datos = $request->validate([
            'nombre'       => ['required', 'string', 'max:150'],
            'especialidad' => ['nullable', 'string', 'max:150'],
            'email'        => ['nullable', 'email', 'max:150'],
            'telefono'     => ['nullable', 'string', 'max:30'],
            'notas'        => ['nullable', 'string', 'max:500'],
            'activo'       => ['boolean'],
        ]);

        $asesor?->exists ? $asesor->update($datos) : Asesor::create($datos);

        return back()->with('success', $asesor?->exists ? 'Asesor actualizado.' : 'Asesor dado de alta.');
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
