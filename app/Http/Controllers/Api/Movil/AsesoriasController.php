<?php

namespace App\Http\Controllers\Api\Movil;

use App\Enums\BolsaDeHoras;
use App\Enums\CategoriaTema;
use App\Exceptions\ErrorDeApi;
use App\Http\Resources\Movil\UsuarioMovil;
use App\Models\Asesor;
use App\Models\Plane;
use App\Models\SolicitudAsesoria;
use App\Models\TemaAsesoria;
use App\Servicios\Asesorias\GestorDeAsesorias;
use App\Servicios\Horas\LibroDeHoras;
use App\Servicios\Membresias\MembresiaDelMiembro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Asesoría IYEM (docs/API-MOVIL.md §6.6).
 *
 * Como en la web: **es una solicitud, no una reserva**. El miembro dice qué día
 * y en qué franja le viene bien; recepción asigna asesor y confirma. En un
 * Match, cada quien pide a su nombre contra la bolsa compartida, con su tope
 * diario propio.
 */
class AsesoriasController extends ControladorMovil
{
    /** Las franjas se ofrecen como opciones: sin agenda de asesores, pedir «11:30» prometería de más. */
    private const FRANJAS = [
        'Por la mañana (9:00 a 12:00)',
        'A mediodía (12:00 a 15:00)',
        'Por la tarde (15:00 a 19:00)',
        'Me acomodo a lo que haya',
    ];

    public function __construct(
        private readonly MembresiaDelMiembro $membresias,
        private readonly GestorDeAsesorias $gestor,
    ) {
    }

    public function index(Request $request, LibroDeHoras $libro): JsonResponse
    {
        $usuario     = $request->user();
        $suscripcion = $this->membresias->vigente($usuario);
        $incluida    = BolsaDeHoras::Asesoria->incluidaEn($suscripcion?->plan);

        return $this->datos([
            'incluida' => $incluida,

            'bolsa' => $incluida ? [
                'cupo'        => BolsaDeHoras::Asesoria->cupo($suscripcion->plan),
                'usado'       => $libro->consumoDelCiclo($suscripcion, BolsaDeHoras::Asesoria),
                'restante'    => $libro->saldoDelCiclo($suscripcion, BolsaDeHoras::Asesoria),
                'tope_diario' => BolsaDeHoras::Asesoria->topeDiario($suscripcion->plan),
                'reinicia_el' => $suscripcion->proximoReinicio()?->toDateString(),
            ] : null,

            'plan_que_la_incluye' => $incluida ? null : Plane::query()
                ->where('activo', true)
                ->whereNotNull(BolsaDeHoras::Asesoria->campoCupo())
                ->orderBy('precio')
                ->first(['id', 'nombre', 'precio', 'periodo_label'])
                ?->only(['id', 'nombre', 'precio', 'periodo_label']),

            'vigencia_hasta' => $suscripcion?->fecha_fin->toDateString(),
            // Vencida: no hay día que elegir; la app lo dice en vez de un
            // selector vacío.
            'vencida'        => (bool) $suscripcion?->vencida(),

            'oferta' => $incluida ? $this->oferta() : [],

            'franjas' => self::FRANJAS,

            'solicitudes' => $usuario->asesorias()
                ->orderByDesc('created_at')
                ->limit(30)
                ->get()
                ->map(fn (SolicitudAsesoria $s) => $this->solicitud($s))
                ->values(),
        ]);
    }

    public function crear(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tema_id'             => ['required', 'integer', 'exists:temas_asesoria,id'],
            'detalle'             => ['nullable', 'string', 'max:500'],
            'asesor_preferido_id' => ['nullable', 'integer', 'exists:asesores,id'],
            'dia_preferido'       => ['required', 'date_format:Y-m-d', 'after_or_equal:' . \App\Models\Reserva::hoyYmd()],
            'horario_preferido'   => ['required', 'string', 'max:120'],
            'horas'               => ['nullable', 'numeric', 'min:0.5', 'max:8'],
        ], [
            'tema_id.required' => 'Elige un tema.',
        ]);

        $suscripcion = $this->membresias->vigente($request->user());

        if (! $suscripcion) {
            throw new ErrorDeApi(403, 'sin_membresia', 'Necesitas una membresía activa para pedir asesoría.');
        }

        if ($suscripcion->vencida()) {
            throw new ErrorDeApi(409, 'membresia_vencida', 'Tu membresía venció el '
                . $suscripcion->fecha_fin->translatedFormat('j \d\e F') . '. Renuévala para pedir asesoría.');
        }

        // Se guarda el nombre del catálogo más el detalle: la bandeja lee una
        // frase completa aunque el catálogo cambie.
        $tema = TemaAsesoria::findOrFail($datos['tema_id'])->nombre;

        if (! empty($datos['detalle'])) {
            $tema .= ' — ' . $datos['detalle'];
        }

        $solicitud = $this->gestor->solicitar(
            suscripcion: $suscripcion,
            tema: $tema,
            diaPreferido: $datos['dia_preferido'],
            horarioPreferido: $datos['horario_preferido'],
            horas: (float) ($datos['horas'] ?? 1),
            asesorPreferidoId: $datos['asesor_preferido_id'] ?? null,
            solicitante: $request->user(),
        );

        return $this->datos([
            'solicitud' => $this->solicitud($solicitud->refresh()),
            'message'   => 'Solicitud enviada. Recepción te confirmará día y asesor; te avisamos en cuanto esté.',
        ], 201);
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $solicitud = $request->user()->asesorias()->whereKey($id)->first();

        abort_unless($solicitud, 404);

        $cancelada = $this->gestor->cancelar($solicitud, $request->user());

        return $this->datos([
            'solicitud' => $this->solicitud($cancelada),
            'message'   => 'Solicitud cancelada.',
        ]);
    }

    private function solicitud(SolicitudAsesoria $s): array
    {
        return [
            'id'                => $s->id,
            'tema'              => $s->tema,
            'dia_preferido'     => $s->dia_preferido->toDateString(),
            'horario_preferido' => $s->horario_preferido,
            'horas'             => (float) $s->horas,
            'estado'            => $s->estado->value,
            'estado_etiqueta'   => $s->estado->etiqueta(),
            'tono'              => $s->estado->tono(),
            'pendiente'         => $s->estado->estaPendiente(),
            'final'             => $s->estado->esFinal(),
            'asesor'            => $s->asesorLegible(),
            'fecha_confirmada'  => $s->fecha_confirmada?->toIso8601String(),
            'notas'             => $s->notas_operativo,
            'solicitada_el'     => $s->created_at?->toIso8601String(),
        ];
    }

    /** Temas activos por categoría, cada uno con los asesores que lo imparten. */
    private function oferta(): array
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
                    'id'       => $a->id,
                    'nombre'   => $a->nombre,
                    'foto_url' => UsuarioMovil::urlPublica($a->foto),
                ])->values(),
            ]),
        ])->filter(fn ($grupo) => $grupo['temas']->isNotEmpty())->values()->all();
    }
}
