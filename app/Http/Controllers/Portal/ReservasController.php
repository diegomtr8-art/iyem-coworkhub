<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Espacio;
use App\Models\Reserva;
use App\Servicios\Horas\ResumenDeBolsas;
use App\Servicios\Membresias\MembresiaDelMiembro;
use App\Servicios\Reservas\Disponibilidad;
use App\Servicios\Reservas\ServicioDeReservas;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Reservas del portal del miembro.
 *
 * Todas las reglas viven en `ServicioDeReservas`, que es también el que usa la
 * API de la app: aquí solo se traduce a pantallas y mensajes. Ver ese servicio
 * para la historia de los BUG-01 a BUG-04.
 */
class ReservasController extends Controller
{
    public function __construct(
        private readonly ServicioDeReservas $reservas,
        private readonly MembresiaDelMiembro $membresias,
        private readonly ResumenDeBolsas $resumen,
    ) {
    }

    public function index(Request $request)
    {
        $user        = $request->user();
        $suscripcion = $this->membresias->vigente($user);

        $proximas = $user->reservas()
            ->with('espacio')
            ->where('fecha', '>=', \App\Models\Reserva::hoyYmd())
            ->confirmadas()
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get()
            ->map(fn (Reserva $reserva) => $this->reservas->paraLaVista($reserva));

        $pasadas = $user->reservas()
            ->with('espacio')
            ->where(fn ($q) => $q->where('fecha', '<', \App\Models\Reserva::hoyYmd())
                ->orWhereIn('estatus', ['Cancelada', 'Completada', 'No_Show']))
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->limit(20)
            ->get()
            ->map(fn (Reserva $reserva) => $this->reservas->paraLaVista($reserva));

        return Inertia::render('Portal/MisReservas', [
            'proximas'    => $proximas,
            'pasadas'     => $pasadas,
            // Solo lo que la vista usa: la membresía trae cargados al titular y al
            // acompañante, y sus modelos completos no tienen por qué salir.
            'suscripcion' => $suscripcion ? [
                'id'        => $suscripcion->id,
                'plan'      => $suscripcion->plan?->only(['id', 'nombre']),
                'fecha_fin' => $suscripcion->fecha_fin->toDateString(),
            ] : null,
        ]);
    }

    public function create(Request $request)
    {
        $suscripcion = $this->membresias->vigente($request->user());

        $espacios = $suscripcion
            ? $this->reservas->espaciosPara($suscripcion)->map(fn (Espacio $espacio) => [
                'id'          => $espacio->id,
                'nombre'      => $espacio->nombre,
                'tipo'        => $espacio->tipo,
                'tipo_label'  => $espacio->tipo_label,
                'capacidad'   => $espacio->capacidad,
                'descripcion' => $espacio->descripcion,
                'imagen'      => $espacio->imagen,
                'amenidades'  => $espacio->amenidades ?? [],

                // Qué bolsa consume: la tarjeta de cada espacio tiene que poder
                // decirlo sin que el miembro cruce datos de dos sitios.
                'bolsa'          => $espacio->bolsa()->value,
                'bolsa_etiqueta' => $espacio->bolsa()->etiqueta(),
            ])
            : collect();

        return Inertia::render('Portal/Reservar', [
            'espacios'    => $espacios,
            'suscripcion' => $suscripcion ? [
                'id'        => $suscripcion->id,
                'plan'      => $suscripcion->plan?->only(['id', 'nombre']),
                'fecha_fin' => $suscripcion->fecha_fin->toDateString(),
            ] : null,
            'medidores'   => $suscripcion ? $this->resumen->soloIncluidas($suscripcion) : [],
            'operacion'   => config('nodico.operacion'),

            // El horizonte de reserva, ya resuelto: el front no tiene que saber
            // sumar los 90 días ni recortar por la vigencia de la membresía.
            'horizonte'   => $suscripcion ? $this->reservas->horizonte($suscripcion) : null,

            // Vencida o a punto: la pantalla lo dice y ofrece renovar, en vez
            // de un calendario sin días.
            'vigencia'    => $suscripcion ? $this->reservas->vigencia($suscripcion) : null,
            'renovarA'    => $suscripcion?->plan ? route('portal.contratar', $suscripcion->plan) : route('membresias'),
        ]);
    }

    /**
     * Disponibilidad de un espacio, para que la pantalla la **enseñe** en vez de
     * que el miembro la descubra al enviar. JSON y no Inertia: se pide al
     * cambiar de espacio o de día sin tirar el resto del formulario.
     */
    public function disponibilidad(Request $request, Disponibilidad $disponibilidad)
    {
        $datos = $request->validate([
            'espacio_id' => ['required', 'exists:espacios,id'],
            'fecha'      => ['required', 'date'],
            'mes'        => ['nullable', 'boolean'],
        ]);

        $espacio = Espacio::findOrFail($datos['espacio_id']);

        // Cada «no» lleva su porqué: la pantalla lo enseña tal cual. Un 403 o
        // un 404 sin texto dejaba el calendario vacío sin explicación.
        if (! $espacio->esReservablePorMiembro()) {
            return response()->json(['motivo' => 'no_reservable', 'mensaje' => 'Este espacio no se reserva en línea. Pregunta en recepción.'], 404);
        }

        $suscripcion = $this->membresias->vigente($request->user());

        if (! $suscripcion) {
            return response()->json(['motivo' => 'sin_membresia', 'mensaje' => 'Necesitas una membresía activa para reservar.'], 403);
        }

        if (! $espacio->bolsa()?->incluidaEn($suscripcion->plan)) {
            return response()->json([
                'motivo'  => 'plan_no_incluye',
                'mensaje' => "Tu plan {$suscripcion->plan?->nombre} no incluye {$espacio->bolsa()?->etiqueta()}.",
            ], 403);
        }

        // «Activa» no basta: nada cambia el estatus al pasar la fecha de fin, y
        // una vencida dejaba el rango del mes al revés (hoy > su último día).
        if ($suscripcion->vencida()) {
            return response()->json([
                'motivo'  => 'membresia_vencida',
                'mensaje' => $this->reservas->vigencia($suscripcion)['mensaje'],
            ], 409);
        }

        $dia = CarbonImmutable::parse($datos['fecha']);

        // El resumen del mes alimenta el calendario; el detalle del día, la
        // franja horaria.
        if ($request->boolean('mes')) {
            return response()->json([
                'dias' => $disponibilidad->delPeriodo(
                    $espacio,
                    $dia->startOfMonth()->max(\App\Models\Reserva::hoy()),
                    $dia->endOfMonth()->min($this->reservas->ultimoDiaReservable($suscripcion)),
                ),
            ]);
        }

        return response()->json($this->reservas->delDia($request->user(), $suscripcion, $espacio, $dia));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'espacio_id'  => ['required', 'exists:espacios,id'],
            'fecha'       => ['required', 'date', 'after_or_equal:' . \App\Models\Reserva::hoyYmd()],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin'    => ['required', 'date_format:H:i', 'after:hora_inicio'],
        ]);

        $this->reservas->reservar($request->user(), $datos);

        return redirect()
            ->route('portal.reservas')
            ->with('success', '¡Reserva confirmada! Te esperamos.');
    }

    public function destroy(Request $request, Reserva $reserva)
    {
        // Solo las propias: en un Match, ni el titular cancela las del
        // acompañante ni al revés.
        if ($reserva->user_id !== $request->user()->id) {
            abort(403);
        }

        $devolvio = $this->reservas->cancelar($reserva);

        if ($devolvio === null) {
            return back()->with('error', 'Esa reserva ya no estaba confirmada.');
        }

        return back()->with('success', $this->reservas->mensajeDeCancelacion($devolvio));
    }
}
