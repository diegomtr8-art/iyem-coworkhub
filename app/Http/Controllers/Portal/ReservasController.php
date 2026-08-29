<?php

namespace App\Http\Controllers\Portal;

use App\Enums\BolsaDeHoras;
use App\Http\Controllers\Controller;
use App\Models\Espacio;
use App\Models\Reserva;
use App\Models\Suscripcion;
use App\Enums\MotivoMovimiento;
use App\Servicios\Horas\LibroDeHoras;
use App\Servicios\Horas\ResumenDeBolsas;
use App\Servicios\Reservas\Disponibilidad;
use App\Servicios\Reservas\RegistroDeBloques;
use App\Servicios\Reservas\ValidadorDeReserva;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Motor de reservas del portal del miembro.
 *
 * Reescrito en la Fase 0 para corregir los siete defectos documentados en
 * `PROMPT-PORTALES-NODICO.md`. Los tres criterios que ordenan el archivo:
 *
 * 1. **La duración se calcula en un solo sitio**, `Reserva::calcularHoras()`.
 *    Los tres cálculos a mano que había aquí devolvían minutos en negativo
 *    (BUG-01) y con ello invertían todo el control de cupos.
 * 2. **La bolsa es la única fuente de verdad** sobre qué puede reservar cada
 *    plan (BUG-02). Ni `incluye_sala_juntas` —que ya no existe— ni listas de
 *    tipos escritas a mano: `TipoEspacio::bolsa()` lo decide.
 * 3. **Nada se comprueba fuera de la transacción** (BUG-03). Y lo que la
 *    transacción no puede garantizar sola lo garantiza el índice único de
 *    `bloques_reserva`.
 */
class ReservasController extends Controller
{
    public function __construct(
        private readonly RegistroDeBloques $bloques,
        private readonly LibroDeHoras $libro,
        private readonly ValidadorDeReserva $calendario,
        private readonly ResumenDeBolsas $resumen,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $proximas = $user->reservas()
            ->with('espacio')
            ->where('fecha', '>=', today())
            ->confirmadas()
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get()
            ->map(fn (Reserva $reserva) => $this->paraLaVista($reserva));

        $pasadas = $user->reservas()
            ->with('espacio')
            ->where(fn ($q) => $q->where('fecha', '<', today())
                ->orWhereIn('estatus', ['Cancelada', 'Completada', 'No_Show']))
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->limit(20)
            ->get()
            ->map(fn (Reserva $reserva) => $this->paraLaVista($reserva));

        return Inertia::render('Portal/MisReservas', [
            'proximas'    => $proximas,
            'pasadas'     => $pasadas,
            'suscripcion' => $this->suscripcionActiva($request),
        ]);
    }

    public function create(Request $request)
    {
        $suscripcion = $this->suscripcionActiva($request);

        $espacios = collect();

        if ($suscripcion) {
            // El filtro sale de la bolsa, igual que la validación de `store`.
            // Antes eran dos criterios distintos y podían contradecirse (BUG-02).
            $espacios = Espacio::reservables()
                ->orderBy('tipo')
                ->orderBy('nombre')
                ->get()
                ->filter(fn (Espacio $espacio) => $espacio->bolsa()?->incluidaEn($suscripcion->plan))
                ->map(fn (Espacio $espacio) => [
                    'id'          => $espacio->id,
                    'nombre'      => $espacio->nombre,
                    'tipo'        => $espacio->tipo,
                    'tipo_label'  => $espacio->tipo_label,
                    'capacidad'   => $espacio->capacidad,
                    'descripcion' => $espacio->descripcion,
                    'imagen'      => $espacio->imagen,
                    'amenidades'  => $espacio->amenidades ?? [],

                    // Qué bolsa consume y cuánto queda de ella: la tarjeta de
                    // cada espacio tiene que poder decirlo sin que el miembro
                    // cruce datos de dos sitios.
                    'bolsa'          => $espacio->bolsa()->value,
                    'bolsa_etiqueta' => $espacio->bolsa()->etiqueta(),
                ])
                ->values();
        }

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
            'horizonte'   => $suscripcion ? [
                'desde' => CarbonImmutable::today()->toDateString(),
                'hasta' => CarbonImmutable::today()
                    ->addDays((int) config('nodico.operacion.antelacion_maxima_dias', 90))
                    ->min(CarbonImmutable::parse($suscripcion->fecha_fin))
                    ->toDateString(),
            ] : null,
        ]);
    }

    /**
     * Disponibilidad de un espacio, para que la pantalla la **enseñe** en vez de
     * que el miembro la descubra al enviar.
     *
     * Devuelve JSON y no una respuesta de Inertia: se pide al cambiar de espacio
     * o de día, y recargar la página entera para repintar una franja horaria
     * sería tirar el resto del formulario.
     */
    public function disponibilidad(Request $request, Disponibilidad $disponibilidad)
    {
        $datos = $request->validate([
            'espacio_id' => ['required', 'exists:espacios,id'],
            'fecha'      => ['required', 'date'],
            'mes'        => ['nullable', 'boolean'],
        ]);

        $espacio = Espacio::findOrFail($datos['espacio_id']);

        if (! $espacio->esReservablePorMiembro()) {
            abort(404);
        }

        $suscripcion = $this->suscripcionActiva($request);

        if (! $suscripcion || ! $espacio->bolsa()?->incluidaEn($suscripcion->plan)) {
            abort(403);
        }

        $dia = CarbonImmutable::parse($datos['fecha']);

        // El resumen del mes alimenta el calendario; el detalle del día, la
        // franja horaria. Son dos consultas distintas porque mandar los 20
        // bloques de cada uno de 90 días serían 1.800 objetos por carga.
        if ($request->boolean('mes')) {
            $tope = CarbonImmutable::today()
                ->addDays((int) config('nodico.operacion.antelacion_maxima_dias', 90))
                ->min(CarbonImmutable::parse($suscripcion->fecha_fin));

            return response()->json([
                'dias' => $disponibilidad->delPeriodo(
                    $espacio,
                    $dia->startOfMonth()->max(CarbonImmutable::today()),
                    $dia->endOfMonth()->min($tope),
                ),
            ]);
        }

        $bolsa = $espacio->bolsa();

        return response()->json([
            ...$disponibilidad->delDia($espacio, $dia),

            // Lo que hace posible la validación en vivo del formulario: sin
            // esto, el front no puede decir «te pasas del tope diario» hasta
            // que el servidor lo rechaza, y descubrir un límite al enviar es
            // justo lo que esta pantalla viene a evitar.
            'bolsa'         => $bolsa->value,
            'saldo_ciclo'   => $this->libro->saldoDelCiclo($suscripcion, $bolsa),
            'tope_diario'   => $bolsa->topeDiario($suscripcion->plan),
            'usado_ese_dia' => $this->horasDeEseDia($suscripcion, $bolsa, $dia->toDateString()),
        ]);
    }

    /** Horas que el miembro ya tiene reservadas de una bolsa en un día concreto. */
    private function horasDeEseDia(Suscripcion $suscripcion, BolsaDeHoras $bolsa, string $fecha): float
    {
        return round(Reserva::where('suscripcion_id', $suscripcion->id)
            ->whereDate('fecha', $fecha)
            ->confirmadas()
            ->whereHas('espacio', fn ($q) => $q->deBolsa($bolsa))
            ->get()
            ->sum(fn (Reserva $reserva) => $reserva->duracionEnHoras()), 2);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'espacio_id'  => ['required', 'exists:espacios,id'],
            'fecha'       => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin'    => ['required', 'date_format:H:i', 'after:hora_inicio'],
        ]);

        $user        = $request->user();
        $espacio     = Espacio::findOrFail($datos['espacio_id']);
        $suscripcion = $this->suscripcionActiva($request);

        if (! $suscripcion) {
            throw ValidationException::withMessages([
                'general' => 'No tienes una membresía activa.',
            ]);
        }

        if (! $espacio->esReservablePorMiembro()) {
            throw ValidationException::withMessages([
                'espacio_id' => $espacio->tipoEnum() === \App\Enums\TipoEspacio::Coworking
                    ? 'El área de coworking no se reserva: entra cuando quieras y haz tu check-in en recepción.'
                    : 'Ese espacio no se puede reservar desde el portal.',
            ]);
        }

        $bolsa = $espacio->bolsa();

        if (! $bolsa->incluidaEn($suscripcion->plan)) {
            throw ValidationException::withMessages([
                'espacio_id' => "Tu plan {$suscripcion->plan?->nombre} no incluye {$bolsa->etiqueta()}.",
            ]);
        }

        // Calendario: horario, festivos, granularidad y antelación. Vive en su
        // propio servicio porque el panel operativo aplica exactamente las
        // mismas reglas y no puede tener otra copia (Fase 1.4).
        $this->calendario->validar(
            espacio: $espacio,
            fecha: $datos['fecha'],
            horaInicio: $datos['hora_inicio'],
            horaFin: $datos['hora_fin'],
        );

        $horas = Reserva::calcularHoras($datos['hora_inicio'], $datos['hora_fin']);

        $this->verificarVigencia($suscripcion, $datos['fecha']);

        // Todo lo que sigue comprueba y escribe **dentro** de la misma
        // transacción. Sacar cualquier comprobación de aquí reabre el BUG-03.
        DB::transaction(function () use ($datos, $user, $espacio, $suscripcion, $bolsa, $horas) {
            // Bloqueo pesimista sobre las reservas de ese espacio y esa fecha:
            // dos peticiones simultáneas se serializan aquí en vez de pasar las
            // dos la comprobación.
            Reserva::where('espacio_id', $espacio->id)
                ->whereDate('fecha', $datos['fecha'])
                ->lockForUpdate()
                ->get();

            $this->verificarTraslape($espacio, $datos);
            $this->verificarTopeDiario($user->id, $suscripcion, $bolsa, $datos['fecha'], $horas);
            $this->verificarSaldo($suscripcion, $bolsa, $horas);

            $reserva = Reserva::create([
                ...$datos,
                'user_id'        => $user->id,
                'suscripcion_id' => $suscripcion->id,
                'estatus'        => 'Confirmada',
                'precio_total'   => 0,
            ]);

            // La red de seguridad de verdad: si otra transacción ganó la carrera
            // entre la comprobación de arriba y este punto, el índice único
            // revienta aquí y la transacción entera se revierte. Se traduce a un
            // error de formulario porque para quien reserva no es un fallo del
            // sistema: alguien se le adelantó por medio segundo.
            try {
                $this->bloques->ocupar($reserva);
            } catch (QueryException $e) {
                if (! $this->esTraslape($e)) {
                    throw $e;
                }

                throw ValidationException::withMessages([
                    'hora_inicio' => 'Alguien acaba de reservar ese horario en '
                        . $espacio->nombre . '. Elige otro.',
                ]);
            }

            // El consumo se anota en el libro, que además deja la caché al
            // día. Ningún sitio del sistema vuelve a tocar los contadores.
            $this->libro->registrar(
                suscripcion: $suscripcion,
                bolsa: $bolsa,
                cantidad: $horas,
                motivo: MotivoMovimiento::Reserva,
                reserva: $reserva,
                autor: $user,
            );
        });

        return redirect()
            ->route('portal.reservas')
            ->with('success', '¡Reserva confirmada! Te esperamos.');
    }

    public function destroy(Request $request, Reserva $reserva)
    {
        if ($reserva->user_id !== $request->user()->id) {
            abort(403);
        }

        $devolvio = DB::transaction(function () use ($reserva) {
            // Se recarga con bloqueo: dos peticiones seguidas devolvían las horas
            // dos veces porque ninguna miraba el estatus antes de tocar nada (BUG-04).
            $fresca = Reserva::whereKey($reserva->getKey())->lockForUpdate()->first();

            if (! $fresca || ! $fresca->estaConfirmada()) {
                return null;
            }

            $devuelve = $fresca->cancelarDevuelveHoras();
            $bolsa    = $fresca->bolsa();

            if ($devuelve && $bolsa && $fresca->suscripcion) {
                // Cantidad negativa: el libro devuelve restando del consumo.
                // Antes esto era un `decrement(..., max(0, $horas))` que con la
                // duración en negativo evaluaba `max(0, -2)` = 0 y no devolvía
                // nada nunca (BUG-01 + BUG-04).
                $this->libro->registrar(
                    suscripcion: $fresca->suscripcion,
                    bolsa: $bolsa,
                    cantidad: -$fresca->duracionEnHoras(),
                    motivo: MotivoMovimiento::Cancelacion,
                    reserva: $fresca,
                    autor: $fresca->user,
                );
            }

            $fresca->update(['estatus' => 'Cancelada']);
            $this->bloques->liberar($fresca);

            return $devuelve;
        });

        if ($devolvio === null) {
            return back()->with('error', 'Esa reserva ya no estaba confirmada.');
        }

        return back()->with('success', $devolvio
            ? 'Reserva cancelada. Las horas vuelven a tu cuenta.'
            : 'Reserva cancelada. Al faltar menos de '
                . config('nodico.operacion.horas_para_cancelar_sin_penalizacion')
                . ' horas para tu reserva, las horas se consumen igual.');
    }

    // ── Comprobaciones ──────────────────────────────────────────────────────

    /**
     * Si la excepción es la violación del índice único de `bloques_reserva` y
     * no otro fallo de base de datos, que no se debe disfrazar de conflicto de
     * horario.
     */
    private function esTraslape(QueryException $e): bool
    {
        return (int) ($e->errorInfo[0] ?? 0) === 23000
            || str_contains($e->getMessage(), 'bloques_reserva_unico')
            || str_contains($e->getMessage(), 'bloques_reserva.espacio_id');
    }

    private function verificarVigencia(Suscripcion $suscripcion, string $fecha): void
    {
        $dia = CarbonImmutable::parse($fecha);

        if ($dia->gt(CarbonImmutable::parse($suscripcion->fecha_fin))) {
            throw ValidationException::withMessages([
                'fecha' => 'La fecha está fuera del período de tu membresía, que termina el '
                    . CarbonImmutable::parse($suscripcion->fecha_fin)->translatedFormat('j \d\e F') . '.',
            ]);
        }
    }

    private function verificarTraslape(Espacio $espacio, array $datos): void
    {
        $ocupado = Reserva::where('espacio_id', $espacio->id)
            ->whereDate('fecha', $datos['fecha'])
            ->confirmadas()
            ->where('hora_inicio', '<', $datos['hora_fin'])
            ->where('hora_fin', '>', $datos['hora_inicio'])
            ->exists();

        if ($ocupado) {
            throw ValidationException::withMessages([
                'hora_inicio' => 'Ese horario ya está ocupado en ' . $espacio->nombre . '. Elige otro.',
            ]);
        }
    }

    /**
     * Tope por día de la bolsa, sumando **todas** las reservas del mismo día que
     * consuman esa bolsa. El código anterior sumaba duraciones negativas, así
     * que el total bajaba con cada reserva y el tope no se alcanzaba nunca.
     */
    private function verificarTopeDiario(
        int $userId,
        Suscripcion $suscripcion,
        BolsaDeHoras $bolsa,
        string $fecha,
        float $horas
    ): void {
        $tope = $bolsa->topeDiario($suscripcion->plan);

        if ($tope === null) {
            return;
        }

        $yaReservadas = Reserva::where('user_id', $userId)
            ->where('suscripcion_id', $suscripcion->id)
            ->whereDate('fecha', $fecha)
            ->confirmadas()
            ->whereHas('espacio', fn ($q) => $q->deBolsa($bolsa))
            ->get()
            ->sum(fn (Reserva $reserva) => $reserva->duracionEnHoras());

        if ($yaReservadas + $horas > $tope) {
            $libres = max(0, round($tope - $yaReservadas, 2));

            throw ValidationException::withMessages([
                'hora_fin' => $libres > 0
                    ? "Tu plan permite {$tope} h al día en {$bolsa->etiqueta()} y ese día ya tienes "
                        . "{$yaReservadas} h. Te queda {$libres} h."
                    : "Tu plan permite {$tope} h al día en {$bolsa->etiqueta()} y ese día ya las usaste.",
            ]);
        }
    }

    private function verificarSaldo(Suscripcion $suscripcion, BolsaDeHoras $bolsa, float $horas): void
    {
        // Se pregunta al libro, no al contador: el contador es una copia y
        // esto es una decisión de negocio.
        $restante = $this->libro->saldoDelCiclo($suscripcion, $bolsa);

        // `null` aquí es «ilimitada», y solo lo es la bolsa de días.
        if ($restante === null) {
            return;
        }

        if ($restante < $horas) {
            throw ValidationException::withMessages([
                'hora_fin' => "No te alcanza: esta reserva son {$horas} h y te quedan {$restante} h "
                    . "de {$bolsa->etiqueta()} en este ciclo.",
            ]);
        }
    }

    // ── Apoyo ───────────────────────────────────────────────────────────────

    private function suscripcionActiva(Request $request): ?Suscripcion
    {
        return $request->user()
            ->suscripciones()
            ->with('plan')
            ->where('estatus', 'Activa')
            ->latest('fecha_inicio')
            ->first();
    }

    /**
     * La reserva con lo que la vista necesita saber y no puede calcular: sobre
     * todo si cancelarla devuelve las horas, que debe verse **antes** de pulsar
     * el botón.
     */
    private function paraLaVista(Reserva $reserva): array
    {
        $bolsa = $reserva->bolsa();

        return [
            ...$reserva->toArray(),

            // `toArray()` serializa `fecha` como ISO completo por el cast
            // `date`, y la vista necesita `Y-m-d` para componer la fecha local
            // sin que el navegador la corra un día por la zona horaria.
            'fecha'                  => $reserva->fecha->toDateString(),
            'horas'                  => $reserva->duracionEnHoras(),
            'bolsa'                  => $bolsa?->value,
            'bolsa_etiqueta'         => $bolsa?->etiqueta(),
            'cancelar_devuelve'      => $reserva->estaConfirmada() && $reserva->cancelarDevuelveHoras(),
            'limite_cancelacion'     => $reserva->inicioEnCalendario()
                ->subHours((int) config('nodico.operacion.horas_para_cancelar_sin_penalizacion'))
                ->toIso8601String(),
            'inicio_en_calendario'   => $reserva->inicioEnCalendario()->toIso8601String(),
        ];
    }
}
