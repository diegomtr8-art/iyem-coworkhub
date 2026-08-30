<?php

namespace App\Http\Controllers;

use App\Enums\AccionOperativa;
use App\Enums\BolsaDeHoras;
use App\Enums\EstadoCuenta;
use App\Models\Checkin;
use App\Models\DatosFiscales;
use App\Models\EntradaBitacora;
use App\Models\MovimientoHoras;
use App\Models\Plane;
use App\Models\Reserva;
use App\Models\SolicitudAsesoria;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Horas\AjusteManual;
use App\Servicios\Horas\ResumenDeBolsas;
use App\Servicios\Membresias\GestorDeMembresias;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Miembros y sus membresías (Fases 3.2 y 3.3).
 *
 * Lo que distingue esta ficha de un CRUD: **el ajuste de horas no edita un
 * contador**. Recepción repone o descuenta con un motivo obligatorio, y eso se
 * convierte en un movimiento del libro. Editar el contador a mano era la vía
 * por la que los saldos se corrompían sin que nadie pudiera reconstruir qué
 * había pasado.
 */
class MiembrosController extends Controller
{
    public function __construct(
        private readonly ResumenDeBolsas $resumen,
        private readonly AjusteManual $ajuste,
        private readonly GestorDeMembresias $membresias,
    ) {
    }

    public function index(Request $request)
    {
        $hoy = today();

        $miembros = User::miembros()
            ->with(['suscripcionActiva.plan:id,nombre,color'])
            ->when($request->string('buscar')->toString(), function ($q, $texto) {
                $like = '%' . $texto . '%';
                $q->where(fn ($s) => $s
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('empresa', 'like', $like)
                    ->orWhere('telefono', 'like', $like));
            })
            ->when($request->integer('plan_id'), fn ($q, $id) => $q->whereHas(
                'suscripciones',
                fn ($s) => $s->where('estatus', 'Activa')->where('plan_id', $id),
            ))
            ->when($request->string('estado')->toString(), function ($q, $estado) use ($hoy) {
                match ($estado) {
                    'activa'   => $q->whereHas('suscripciones', fn ($s) => $s->where('estatus', 'Activa')
                                     ->whereDate('fecha_fin', '>=', $hoy)),
                    'vencida'  => $q->whereDoesntHave('suscripciones', fn ($s) => $s->where('estatus', 'Activa')
                                     ->whereDate('fecha_fin', '>=', $hoy)),
                    'por_vencer' => $q->whereHas('suscripciones', fn ($s) => $s->where('estatus', 'Activa')
                                     ->whereBetween('fecha_fin', [$hoy, $hoy->copy()->addDays(7)])),
                    'suspendida' => $q->where('estado_cuenta', EstadoCuenta::Suspendida->value),
                    'sin_faceid' => $q->where('face_id_ok', false),
                    default    => null,
                };
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id'        => $u->id,
                'nombre'    => $u->name,
                'email'     => $u->email,
                'telefono'  => $u->telefono,
                'empresa'   => $u->empresa,
                'face_id_ok' => (bool) $u->face_id_ok,
                'estado_cuenta' => $u->estado->value,
                'plan'      => $u->suscripcionActiva?->plan?->nombre,
                'vence'     => $u->suscripcionActiva?->fecha_fin?->toDateString(),
                'dias_para_vencer' => $u->suscripcionActiva
                    ? (int) $hoy->diffInDays($u->suscripcionActiva->fecha_fin, false)
                    : null,
            ]);

        return Inertia::render('Miembros/Index', [
            'miembros' => $miembros,
            'planes'   => Plane::where('activo', true)->orderBy('orden')->get(['id', 'nombre']),
            'filtros'  => $request->only(['buscar', 'plan_id', 'estado']),
        ]);
    }

    public function show(Request $request, User $miembro)
    {
        $suscripcion = $miembro->suscripciones()
            ->with('plan', 'companion:id,name,email')
            ->where('estatus', 'Activa')
            ->latest('fecha_inicio')
            ->first();

        // Los datos fiscales son sensibles: solo administración, y su lectura
        // se registra. Recepción ve la ficha completa **menos** esto.
        $puedeVerFiscales = $request->user()->can('verDeMiembro', [DatosFiscales::class, $miembro]);
        $fiscales         = null;

        if ($puedeVerFiscales && $miembro->datosFiscales) {
            $fiscales = [
                'rfc'            => $miembro->datosFiscales->rfc,
                'razon_social'   => $miembro->datosFiscales->razon_social,
                'regimen'        => $miembro->datosFiscales->regimenLegible(),
                'uso_cfdi'       => $miembro->datosFiscales->usoCfdiLegible(),
                'codigo_postal'  => $miembro->datosFiscales->codigo_postal,
                'email'          => $miembro->datosFiscales->email_facturacion,
                'completos'      => $miembro->datosFiscales->estanCompletos(),
            ];

            $this->registrarConsultaFiscal($request->user(), $miembro);
        }

        return Inertia::render('Miembros/Show', [
            'miembro' => [
                'id'        => $miembro->id,
                'nombre'    => $miembro->name,
                'email'     => $miembro->email,
                'telefono'  => $miembro->telefono,
                'empresa'   => $miembro->empresa,
                'ocupacion' => $miembro->ocupacion,
                'avatar'    => $miembro->avatar,
                'face_id_ok' => (bool) $miembro->face_id_ok,
                'estado_cuenta' => $miembro->estado->value,
                'notas_admin'   => $miembro->notas_admin,
                'alta'          => $miembro->created_at?->toDateString(),
                'emergencia'    => [
                    'nombre'     => $miembro->contacto_emergencia_nombre,
                    'telefono'   => $miembro->contacto_emergencia_telefono,
                    'parentesco' => $miembro->contacto_emergencia_parentesco,
                ],
            ],

            'suscripcion' => $suscripcion ? [
                'id'            => $suscripcion->id,
                'plan'          => $suscripcion->plan?->only(['id', 'nombre', 'precio', 'personas', 'color']),
                'fecha_inicio'  => $suscripcion->fecha_inicio->toDateString(),
                'fecha_fin'     => $suscripcion->fecha_fin->toDateString(),
                'estatus'       => $suscripcion->estatus,
                'precio_pagado' => (float) $suscripcion->precio_pagado,
                'ciclo_inicio'  => $suscripcion->cicloInicio()->toDateString(),
                'ciclo_fin'     => $suscripcion->cicloFin()->toDateString(),
                'companion'     => $suscripcion->companion?->only(['id', 'name', 'email']),
                'companion_face_id_ok' => (bool) $suscripcion->companion_face_id_ok,
                'admite_companion'     => ($suscripcion->plan?->personas ?? 1) > 1,
            ] : null,

            'medidores' => $suscripcion ? $this->resumen->soloIncluidas($suscripcion) : [],

            'historialMembresias' => $miembro->suscripciones()
                ->with('plan:id,nombre')
                ->when($suscripcion, fn ($q) => $q->whereKeyNot($suscripcion->id))
                ->orderByDesc('fecha_inicio')
                ->limit(10)
                ->get()
                ->map(fn (Suscripcion $s) => [
                    'id'      => $s->id,
                    'plan'    => $s->plan?->nombre,
                    'desde'   => $s->fecha_inicio->toDateString(),
                    'hasta'   => $s->fecha_fin->toDateString(),
                    'estatus' => $s->estatus,
                    'precio'  => (float) $s->precio_pagado,
                ]),

            'reservas' => $miembro->reservas()
                ->with('espacio:id,nombre')
                ->orderByDesc('fecha')
                ->limit(15)
                ->get()
                ->map(fn (Reserva $r) => [
                    'id'      => $r->id,
                    'espacio' => $r->espacio?->nombre,
                    'fecha'   => $r->fecha->toDateString(),
                    'inicio'  => substr($r->hora_inicio, 0, 5),
                    'fin'     => substr($r->hora_fin, 0, 5),
                    'horas'   => $r->duracionEnHoras(),
                    'estatus' => $r->estatus,
                ]),

            'accesos' => $miembro->checkins()
                ->with('espacio:id,nombre')
                ->latest('hora_entrada')
                ->limit(15)
                ->get()
                ->map(fn (Checkin $c) => [
                    'id'      => $c->id,
                    'espacio' => $c->espacio?->nombre,
                    'entrada' => $c->hora_entrada->toIso8601String(),
                    'salida'  => $c->hora_salida?->toIso8601String(),
                    'minutos' => $c->duracion_minutos,
                ]),

            'movimientos' => $suscripcion
                ? MovimientoHoras::where('suscripcion_id', $suscripcion->id)
                    ->with('creadoPor:id,name')
                    ->latest('id')
                    ->limit(25)
                    ->get()
                    ->map(fn (MovimientoHoras $m) => [
                        'id'          => $m->id,
                        'descripcion' => $m->descripcion(),
                        'bolsa'       => $m->bolsa->value,
                        'motivo'      => $m->motivo->value,
                        'nota'        => $m->nota,
                        'autor'       => $m->creadoPor?->name,
                        'fecha'       => $m->created_at->toIso8601String(),
                    ])
                : [],

            'asesorias' => $miembro->asesorias()
                ->with('asesor:id,nombre')
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (SolicitudAsesoria $s) => [
                    'id'     => $s->id,
                    'tema'   => $s->tema,
                    'estado' => $s->estado->value,
                    'tono'   => $s->estado->tono(),
                    'dia'    => $s->dia_preferido->toDateString(),
                    'asesor' => $s->asesorLegible(),
                ]),

            'facturas' => $miembro->facturas()
                ->orderByDesc('fecha')
                ->limit(10)
                ->get(['id', 'folio', 'concepto', 'fecha', 'total', 'estatus']),

            'datosFiscales'      => $fiscales,
            'puedeVerFiscales'   => $puedeVerFiscales,

            'planes'       => Plane::where('activo', true)->orderBy('orden')->get(['id', 'nombre', 'precio', 'personas']),
            'candidatosCompanion' => $suscripcion && ($suscripcion->plan?->personas ?? 1) > 1
                ? User::miembros()->whereKeyNot($miembro->id)->orderBy('name')->get(['id', 'name', 'email'])
                : [],
            'bolsas' => collect(BolsaDeHoras::cases())
                ->filter(fn (BolsaDeHoras $b) => $suscripcion && $b->incluidaEn($suscripcion->plan))
                ->map(fn (BolsaDeHoras $b) => ['valor' => $b->value, 'etiqueta' => $b->etiqueta()])
                ->values(),
        ]);
    }

    // ── 3.2 · Ajuste manual de horas ────────────────────────────────────────

    public function ajustarHoras(Request $request, User $miembro)
    {
        $datos = $request->validate([
            'suscripcion_id' => ['required', 'exists:suscripciones,id'],
            'bolsa'          => ['required', Rule::enum(BolsaDeHoras::class)],
            'horas'          => ['required', 'numeric', 'not_in:0', 'between:-100,100'],
            'motivo'         => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'motivo.required' => 'Escribe por qué ajustas las horas. Queda en la bitácora.',
            'motivo.min'      => 'Da un motivo un poco más explícito: alguien va a leerlo dentro de seis meses.',
            'horas.not_in'    => 'El ajuste no puede ser de cero horas.',
        ]);

        $suscripcion = Suscripcion::with('plan', 'user')->findOrFail($datos['suscripcion_id']);

        abort_unless($suscripcion->user_id === $miembro->id, 403);

        $this->ajuste->aplicar(
            suscripcion: $suscripcion,
            bolsa: BolsaDeHoras::from($datos['bolsa']),
            horas: (float) $datos['horas'],
            motivo: $datos['motivo'],
            operativo: $request->user(),
        );

        return back()->with('success', 'Horas ajustadas y anotadas en la bitácora.');
    }

    public function notas(Request $request, User $miembro)
    {
        $datos = $request->validate(['notas_admin' => ['nullable', 'string', 'max:2000']]);

        $miembro->update($datos);

        return back()->with('success', 'Notas guardadas.');
    }

    // ── 3.3 · Membresías ────────────────────────────────────────────────────

    public function crearSuscripcion(Request $request, User $miembro)
    {
        $datos = $request->validate([
            'plan_id'       => ['required', 'exists:planes,id'],
            'fecha_inicio'  => ['nullable', 'date'],
            'precio_pagado' => ['nullable', 'numeric', 'min:0'],
            'nota'          => ['nullable', 'string', 'max:500'],
        ]);

        $this->membresias->alta(
            miembro: $miembro,
            plan: Plane::findOrFail($datos['plan_id']),
            operativo: $request->user(),
            fechaInicio: $datos['fecha_inicio'] ?? null,
            precioPagado: isset($datos['precio_pagado']) ? (float) $datos['precio_pagado'] : null,
            nota: $datos['nota'] ?? null,
        );

        return back()->with('success', 'Membresía activada.');
    }

    public function cambiarPlan(Request $request, User $miembro)
    {
        $datos = $request->validate([
            'suscripcion_id' => ['required', 'exists:suscripciones,id'],
            'plan_id'        => ['required', 'exists:planes,id'],
            'motivo'         => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'motivo.required' => 'Escribe por qué cambia de plan.',
        ]);

        $suscripcion = Suscripcion::with('plan', 'user')->findOrFail($datos['suscripcion_id']);
        abort_unless($suscripcion->user_id === $miembro->id, 403);

        $this->membresias->cambiarDePlan(
            suscripcion: $suscripcion,
            planNuevo: Plane::findOrFail($datos['plan_id']),
            operativo: $request->user(),
            motivo: $datos['motivo'],
        );

        return back()->with(
            'success',
            'Plan cambiado. Se abrió un ciclo nuevo: sus bolsas arrancan completas desde hoy.'
        );
    }

    public function renovar(Request $request, User $miembro)
    {
        $datos = $request->validate([
            'suscripcion_id' => ['required', 'exists:suscripciones,id'],
            'precio_pagado'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $suscripcion = Suscripcion::with('plan', 'user')->findOrFail($datos['suscripcion_id']);
        abort_unless($suscripcion->user_id === $miembro->id, 403);

        $nueva = $this->membresias->renovar(
            $suscripcion,
            $request->user(),
            isset($datos['precio_pagado']) ? (float) $datos['precio_pagado'] : null,
        );

        return back()->with('success', 'Renovada hasta el ' . $nueva->fecha_fin->translatedFormat('j \d\e F') . '.');
    }

    public function suspender(Request $request, User $miembro)
    {
        $datos = $request->validate([
            'suscripcion_id' => ['required', 'exists:suscripciones,id'],
            'motivo'         => ['required', 'string', 'min:5', 'max:500'],
        ], ['motivo.required' => 'Escribe por qué se suspende.']);

        $suscripcion = Suscripcion::with('plan', 'user')->findOrFail($datos['suscripcion_id']);
        abort_unless($suscripcion->user_id === $miembro->id, 403);

        $this->membresias->suspender($suscripcion, $request->user(), $datos['motivo']);

        return back()->with('success', 'Membresía suspendida.');
    }

    public function reactivar(Request $request, User $miembro)
    {
        $datos = $request->validate(['suscripcion_id' => ['required', 'exists:suscripciones,id']]);

        $suscripcion = Suscripcion::with('plan', 'user')->findOrFail($datos['suscripcion_id']);
        abort_unless($suscripcion->user_id === $miembro->id, 403);

        $this->membresias->reactivar($suscripcion, $request->user());

        return back()->with('success', 'Membresía reactivada.');
    }

    // ── Face ID y acompañante ───────────────────────────────────────────────

    public function toggleFaceId(Request $request, User $miembro)
    {
        $miembro->update(['face_id_ok' => ! $miembro->face_id_ok]);

        return back()->with('success', $miembro->fresh()->face_id_ok
            ? 'Face ID registrado.'
            : 'Face ID retirado.');
    }

    public function setCompanion(Request $request, User $miembro)
    {
        $datos = $request->validate([
            'suscripcion_id'       => ['required', 'exists:suscripciones,id'],
            'companion_user_id'    => ['nullable', 'exists:users,id'],
            'companion_face_id_ok' => ['boolean'],
        ]);

        $suscripcion = Suscripcion::with('plan')->findOrFail($datos['suscripcion_id']);
        abort_unless($suscripcion->user_id === $miembro->id, 403);

        // Un plan de una persona no admite acompañante: guardarlo daría a
        // entender que hay un segundo acceso que no existe.
        if (($suscripcion->plan?->personas ?? 1) < 2 && filled($datos['companion_user_id'] ?? null)) {
            return back()->withErrors([
                'companion_user_id' => 'El plan ' . $suscripcion->plan?->nombre . ' es para una persona.',
            ]);
        }

        if (($datos['companion_user_id'] ?? null) == $miembro->id) {
            return back()->withErrors([
                'companion_user_id' => 'El acompañante tiene que ser otra persona.',
            ]);
        }

        $suscripcion->update([
            'companion_user_id'    => $datos['companion_user_id'] ?? null,
            'companion_face_id_ok' => $datos['companion_face_id_ok'] ?? false,
        ]);

        return back()->with('success', 'Acompañante actualizado.');
    }

    /** Agrupada por hora, para que navegar no llene la bitácora. */
    private function registrarConsultaFiscal(User $operativo, User $miembro): void
    {
        if ($operativo->id === $miembro->id) {
            return;
        }

        $reciente = EntradaBitacora::query()
            ->where('actor_user_id', $operativo->id)
            ->where('sujeto_user_id', $miembro->id)
            ->deAccion(AccionOperativa::ConsultaDatosFiscales)
            ->where('created_at', '>=', now()->subHour())
            ->exists();

        if ($reciente) {
            return;
        }

        EntradaBitacora::registrar(
            accion: AccionOperativa::ConsultaDatosFiscales,
            descripcion: "Consultó los datos fiscales de {$miembro->name}.",
            actor: $operativo,
            sujeto: $miembro,
        );
    }
}
