<?php

namespace App\Servicios\Reservas;

use App\Enums\BolsaDeHoras;
use App\Enums\MotivoMovimiento;
use App\Enums\TipoEspacio;
use App\Models\Espacio;
use App\Models\Reserva;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Horas\LibroDeHoras;
use App\Servicios\Membresias\MembresiaDelMiembro;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El motor de reservas del miembro: una sola copia para el portal web y la app.
 *
 * Vivía dentro de `Portal\ReservasController` (reescrito en la Fase 0 del
 * portal para cerrar BUG-01 a BUG-04). Se sacó aquí el 22/09/2026 para que la
 * API móvil aplique exactamente las mismas reglas: dos motores de reglas
 * siempre terminan discrepando.
 *
 * Los criterios que ordenan el archivo siguen siendo los de entonces:
 *
 * 1. **La duración se calcula en un solo sitio**, `Reserva::calcularHoras()`.
 * 2. **La bolsa es la única fuente de verdad** sobre qué puede reservar cada
 *    plan: `TipoEspacio::bolsa()` lo decide.
 * 3. **Nada se comprueba fuera de la transacción**, y lo que la transacción no
 *    garantiza sola lo garantiza el índice único de `bloques_reserva`.
 *
 * Y uno nuevo: **el tope diario es por persona** (decisión del 22/09/2026). En
 * un Nodo Match titular y acompañante consumen la misma bolsa, pero cada uno
 * tiene su tope al día.
 */
class ServicioDeReservas
{
    public function __construct(
        private readonly RegistroDeBloques $bloques,
        private readonly LibroDeHoras $libro,
        private readonly ValidadorDeReserva $calendario,
        private readonly MembresiaDelMiembro $membresias,
        private readonly Disponibilidad $disponibilidad,
    ) {
    }

    // ── Lectura ─────────────────────────────────────────────────────────────

    /**
     * Los espacios que esta membresía puede reservar. El filtro sale de la
     * bolsa, igual que la validación de `reservar()`.
     *
     * @return Collection<int, Espacio>
     */
    public function espaciosPara(Suscripcion $suscripcion): Collection
    {
        return Espacio::reservables()
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get()
            ->filter(fn (Espacio $espacio) => $espacio->bolsa()?->incluidaEn($suscripcion->plan))
            ->values();
    }

    /** @return array{desde: string, hasta: string} */
    public function horizonte(Suscripcion $suscripcion): array
    {
        return [
            'desde' => \App\Models\Reserva::hoy()->toDateString(),
            'hasta' => $this->ultimoDiaReservable($suscripcion)->toDateString(),
        ];
    }

    public function ultimoDiaReservable(Suscripcion $suscripcion): CarbonImmutable
    {
        return \App\Models\Reserva::hoy()
            ->addDays((int) config('nodico.operacion.antelacion_maxima_dias', 90))
            ->min(CarbonImmutable::parse($suscripcion->fecha_fin->toDateString(), Reserva::zonaDelCalendario()));
    }

    /**
     * La franja de un día más lo que hace posible la validación en vivo: sin
     * saldo, tope y lo ya usado ese día, la pantalla no puede decir «te pasas
     * del tope» hasta que el servidor lo rechaza.
     */
    public function delDia(User $usuario, Suscripcion $suscripcion, Espacio $espacio, CarbonImmutable $dia): array
    {
        $bolsa = $espacio->bolsa();

        return [
            ...$this->disponibilidad->delDia($espacio, $dia),
            'bolsa'         => $bolsa->value,
            'saldo_ciclo'   => $this->libro->saldoDelCiclo($suscripcion, $bolsa),
            'tope_diario'   => $bolsa->topeDiario($suscripcion->plan),
            'usado_ese_dia' => $this->horasDeEseDia($usuario, $suscripcion, $bolsa, $dia->toDateString()),
        ];
    }

    /**
     * Horas que **esta persona** ya tiene reservadas de una bolsa en un día.
     *
     * Antes la disponibilidad contaba por membresía y la reserva por persona:
     * en un Match la pantalla podía decir «ya no te queda» cuando sí quedaba.
     */
    public function horasDeEseDia(User $usuario, Suscripcion $suscripcion, BolsaDeHoras $bolsa, string $fecha): float
    {
        return round(Reserva::where('user_id', $usuario->id)
            ->where('suscripcion_id', $suscripcion->id)
            ->whereDate('fecha', $fecha)
            ->confirmadas()
            ->whereHas('espacio', fn ($q) => $q->deBolsa($bolsa))
            ->get()
            ->sum(fn (Reserva $reserva) => $reserva->duracionEnHoras()), 2);
    }

    /**
     * La reserva con lo que la vista necesita y no puede calcular: sobre todo si
     * cancelarla devuelve las horas, que debe verse **antes** de pulsar.
     */
    public function paraLaVista(Reserva $reserva): array
    {
        $bolsa = $reserva->bolsa();

        return [
            ...$reserva->toArray(),

            // `toArray()` serializa `fecha` como ISO completo por el cast
            // `date`, y la vista necesita `Y-m-d`.
            'fecha'                => $reserva->fecha->toDateString(),
            'horas'                => $reserva->duracionEnHoras(),
            'bolsa'                => $bolsa?->value,
            'bolsa_etiqueta'       => $bolsa?->etiqueta(),
            'cancelar_devuelve'    => $reserva->estaConfirmada() && $reserva->cancelarDevuelveHoras(),
            'limite_cancelacion'   => $this->limiteDeCancelacion($reserva),
            'inicio_en_calendario' => $reserva->inicioEnCalendario()->toIso8601String(),
        ];
    }

    public function limiteDeCancelacion(Reserva $reserva): string
    {
        return $reserva->inicioEnCalendario()
            ->subHours((int) config('nodico.operacion.horas_para_cancelar_sin_penalizacion'))
            ->toIso8601String();
    }

    public function mensajeDeCancelacion(bool $devolvio): string
    {
        return $devolvio
            ? 'Reserva cancelada. Las horas vuelven a tu cuenta.'
            : 'Reserva cancelada. Al faltar menos de '
                . config('nodico.operacion.horas_para_cancelar_sin_penalizacion')
                . ' horas para tu reserva, las horas se consumen igual.';
    }

    // ── Escritura ───────────────────────────────────────────────────────────

    /**
     * @param  array{espacio_id: int|string, fecha: string, hora_inicio: string, hora_fin: string}  $datos
     *
     * @throws ValidationException con el texto exacto de la regla que falló
     */
    public function reservar(User $usuario, array $datos): Reserva
    {
        $espacio     = Espacio::findOrFail($datos['espacio_id']);
        $suscripcion = $this->membresias->vigente($usuario);

        if (! $suscripcion) {
            throw ValidationException::withMessages([
                'general' => 'No tienes una membresía activa.',
            ]);
        }

        if (! $espacio->esReservablePorMiembro()) {
            throw ValidationException::withMessages([
                'espacio_id' => $espacio->tipoEnum() === TipoEspacio::Coworking
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

        // Calendario: horario, festivos, granularidad y antelación. El panel
        // operativo aplica exactamente las mismas reglas (Fase 1.4).
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
        return DB::transaction(function () use ($datos, $usuario, $espacio, $suscripcion, $bolsa, $horas) {
            // Primero la suscripción: el saldo y el tope son de la bolsa, no del
            // espacio. Sin este bloqueo, dos reservas simultáneas en salas
            // distintas (o del titular y el acompañante de un Match) pasaban
            // las dos la comprobación de saldo y dejaban la bolsa en negativo.
            // Mismo orden de bloqueo que `cancelar()`, para no cruzarse.
            Suscripcion::whereKey($suscripcion->getKey())->lockForUpdate()->first();

            // Bloqueo pesimista sobre las reservas de ese espacio y esa fecha:
            // dos peticiones simultáneas se serializan aquí.
            Reserva::where('espacio_id', $espacio->id)
                ->whereDate('fecha', $datos['fecha'])
                ->lockForUpdate()
                ->get();

            $this->verificarTraslape($espacio, $datos);
            $this->verificarTopeDiario($usuario, $suscripcion, $bolsa, $datos['fecha'], $horas);
            $this->verificarSaldo($suscripcion, $bolsa, $horas);

            $reserva = Reserva::create([
                'espacio_id'     => $espacio->id,
                'fecha'          => $datos['fecha'],
                'hora_inicio'    => $datos['hora_inicio'],
                'hora_fin'       => $datos['hora_fin'],
                'user_id'        => $usuario->id,
                'suscripcion_id' => $suscripcion->id,
                'estatus'        => 'Confirmada',
                'precio_total'   => 0,
            ]);

            // La red de seguridad de verdad: si otra transacción ganó la carrera,
            // el índice único revienta aquí y se revierte todo.
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

            // El consumo se anota en el libro, que además deja la caché al día.
            $this->libro->registrar(
                suscripcion: $suscripcion,
                bolsa: $bolsa,
                cantidad: $horas,
                motivo: MotivoMovimiento::Reserva,
                reserva: $reserva,
                autor: $usuario,
            );

            return $reserva;
        });
    }

    /**
     * Cancela una reserva **propia**. Devuelve si devolvió las horas, o `null`
     * si ya no estaba confirmada. Quien llama comprueba antes que es suya.
     */
    public function cancelar(Reserva $reserva): ?bool
    {
        return DB::transaction(function () use ($reserva) {
            // La suscripción antes que la reserva: el mismo orden que `reservar()`.
            if ($reserva->suscripcion_id) {
                Suscripcion::whereKey($reserva->suscripcion_id)->lockForUpdate()->first();
            }

            // Se recarga con bloqueo: dos peticiones seguidas devolvían las horas
            // dos veces porque ninguna miraba el estatus (BUG-04).
            $fresca = Reserva::whereKey($reserva->getKey())->lockForUpdate()->first();

            if (! $fresca || ! $fresca->estaConfirmada()) {
                return null;
            }

            $devuelve = $fresca->cancelarDevuelveHoras();
            $bolsa    = $fresca->bolsa();

            if ($devuelve && $bolsa && $fresca->suscripcion) {
                // Cantidad negativa: el libro devuelve restando del consumo.
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
    }

    // ── Comprobaciones ──────────────────────────────────────────────────────

    /**
     * Si la excepción es la violación del índice único de `bloques_reserva` y
     * no otro fallo, que no se debe disfrazar de conflicto de horario.
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

    /** Tope por día de la bolsa, por persona, sumando todas sus reservas de ese día. */
    private function verificarTopeDiario(
        User $usuario,
        Suscripcion $suscripcion,
        BolsaDeHoras $bolsa,
        string $fecha,
        float $horas
    ): void {
        $tope = $bolsa->topeDiario($suscripcion->plan);

        if ($tope === null) {
            return;
        }

        $yaReservadas = $this->horasDeEseDia($usuario, $suscripcion, $bolsa, $fecha);

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
        // Se pregunta al libro, no al contador: el contador es una copia.
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
}
