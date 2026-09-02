<?php

namespace App\Servicios\Tablero;

use App\Enums\TipoEspacio;
use App\Models\BloqueoEspacio;
use App\Models\Checkin;
use App\Models\Espacio;
use App\Models\Reserva;
use App\Servicios\Reservas\ValidadorDeReserva;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * El motor del tablero en vivo (Fase 2): calcula el estado de cada espacio a
 * partir de reservas, bloqueos, horario/festivos y check-ins.
 *
 * **Solo emite datos del espacio, jamás de personas.** No devuelve nombres,
 * correos ni user_id: la pantalla va en zona pública. Lo que el tablero no
 * dibuja tampoco viaja en el JSON (ver la prueba del endpoint público).
 *
 * Los salones de eventos (Yucatán Emprende) quedan fuera por decisión de Nódico.
 */
class EstadoDeEspacios
{
    /** Minutos antes de una reserva en que el espacio pasa a «aparta pronto». */
    private const MARGEN_PRONTO = 30;

    /** Horas por defecto de jornada si un espacio no declara horario. */
    private const JORNADA_POR_DEFECTO = 12;

    public function __construct(private readonly ValidadorDeReserva $calendario)
    {
    }

    /** @return array<int, array<string, mixed>> el estado de cada espacio del tablero. */
    public function paraTablero(?CarbonImmutable $ahora = null): array
    {
        $ahora = $ahora ?? CarbonImmutable::now();
        $hoy   = $ahora->startOfDay();

        $espacios = Espacio::query()
            ->where('disponible', true)
            ->where('tipo', '!=', TipoEspacio::SalonEventos->value)
            ->orderBy('tipo')->orderBy('nombre')
            ->get();

        $reservas = Reserva::whereDate('fecha', $hoy)->confirmadas()
            ->orderBy('hora_inicio')->get()->groupBy('espacio_id');
        $bloqueos = BloqueoEspacio::whereDate('fecha', $hoy)->get()->groupBy('espacio_id');

        return $espacios->map(fn (Espacio $e) => $this->estadoDe(
            $e, $ahora, $hoy,
            $reservas->get($e->id, collect()),
            $bloqueos->get($e->id, collect()),
        ))->values()->all();
    }

    /** Resumen para la franja superior del tablero. */
    public function resumen(array $espacios): array
    {
        $salas = array_filter($espacios, fn ($e) => $e['estado'] !== 'coworking');
        $libres = array_filter($salas, fn ($e) => $e['estado'] === 'libre');
        $cowork = array_values(array_filter($espacios, fn ($e) => $e['estado'] === 'coworking'))[0] ?? null;

        return [
            'salas_libres'   => count($libres),
            'salas_totales'  => count($salas),
            'coworking_pct'  => $cowork['porcentaje'] ?? null,
            'coworking_dentro' => $cowork['personas_dentro'] ?? null,
        ];
    }

    /**
     * La agenda de hoy de un espacio, para el toque (Fase 4): franja de apertura
     * y los bloques ocupados **solo con su horario, sin nombres**. Los huecos los
     * calcula el front (son lo que la persona busca).
     */
    public function agendaDe(Espacio $espacio, ?CarbonImmutable $ahora = null): array
    {
        $ahora = $ahora ?? CarbonImmutable::now();
        $hoy   = $ahora->startOfDay();
        $franja = $this->calendario->franjaDelDia($espacio, $hoy);

        $ocupados = collect();

        Reserva::whereDate('fecha', $hoy)->where('espacio_id', $espacio->id)->confirmadas()
            ->orderBy('hora_inicio')->get()
            ->each(fn (Reserva $r) => $ocupados->push([
                'inicio' => substr((string) $r->hora_inicio, 0, 5),
                'fin'    => substr((string) $r->hora_fin, 0, 5),
                'tipo'   => 'reserva',
            ]));

        BloqueoEspacio::whereDate('fecha', $hoy)->where('espacio_id', $espacio->id)->get()
            ->each(fn (BloqueoEspacio $b) => $ocupados->push([
                'inicio' => substr((string) $b->hora_inicio, 0, 5),
                'fin'    => substr((string) $b->hora_fin, 0, 5),
                'tipo'   => 'bloqueo',
            ]));

        return [
            'id'        => $espacio->id,
            'nombre'    => $espacio->nombre,
            'tipo'      => (string) $espacio->tipo,
            'capacidad' => $espacio->capacidad,
            'abierto'   => $franja !== null,
            'apertura'  => $franja[0] ?? null,
            'cierre'    => $franja[1] ?? null,
            'ocupados'  => $ocupados->sortBy('inicio')->values()->all(),
        ];
    }

    // ── Interno ────────────────────────────────────────────────────────────────

    private function estadoDe(
        Espacio $espacio, CarbonImmutable $ahora, CarbonImmutable $hoy,
        Collection $reservas, Collection $bloqueos,
    ): array {
        $base = [
            'id'         => $espacio->id,
            'nombre'     => $espacio->nombre,
            'tipo'       => (string) $espacio->tipo,   // la columna es cadena, no enum
            'capacidad'  => $espacio->capacidad,
        ];

        // El coworking no tiene los 5 estados de sala: se mide por % dentro.
        if ($espacio->tipo === TipoEspacio::Coworking->value) {
            $franja = $this->calendario->franjaDelDia($espacio, $hoy);
            [$dentro, $pct] = $this->ocupacionCoworking($espacio, $ahora, $franja);

            return $base + [
                'estado'          => 'coworking',
                'personas_dentro' => $dentro,
                'porcentaje'      => $pct,
                'fuera_horario'   => $franja === null || ! $this->dentroDeFranja($ahora, $franja),
            ];
        }

        // Fuera de horario / festivo: gana sobre todo lo demás salvo el bloqueo.
        $franja = $this->calendario->franjaDelDia($espacio, $hoy);
        if ($franja === null || ! $this->dentroDeFranja($ahora, $franja)) {
            return $base + ['estado' => 'fuera_horario', 'abre' => $franja[0] ?? null];
        }

        // Bloqueada (mantenimiento / evento privado): manda sobre reservas.
        foreach ($bloqueos as $b) {
            if ($this->vigenteAhora($b->hora_inicio, $b->hora_fin, $hoy, $ahora)) {
                return $base + ['estado' => 'bloqueada', 'hasta' => substr((string) $b->hora_fin, 0, 5)];
            }
        }

        // Ocupada ahora: lo único que importa es hasta qué hora se libera.
        foreach ($reservas as $r) {
            if ($r->inicioEnCalendario()->lte($ahora) && $r->finEnCalendario()->gt($ahora)) {
                return $base + ['estado' => 'ocupada', 'hasta' => substr((string) $r->hora_fin, 0, 5)];
            }
        }

        // Aparta pronto: próxima reserva del día dentro del margen.
        foreach ($reservas as $r) {
            $inicio = $r->inicioEnCalendario();
            if ($inicio->gt($ahora) && $inicio->lte($ahora->addMinutes(self::MARGEN_PRONTO))) {
                return $base + ['estado' => 'aparta_pronto', 'desde' => substr((string) $r->hora_inicio, 0, 5)];
            }
        }

        return $base + ['estado' => 'libre'];
    }

    /**
     * Personas dentro del coworking, contra la capacidad.
     *
     * **La trampa del check-in colgado:** si alguien entra y nunca marca salida,
     * el conteo se infla el resto del día. Aquí no se cuentan los check-ins
     * abiertos más tiempo que la jornada, y se deja constancia para recepción.
     */
    private function ocupacionCoworking(Espacio $espacio, CarbonImmutable $ahora, ?array $franja): array
    {
        $jornadaHoras = $franja
            ? max(1, ($this->aMin($franja[1]) - $this->aMin($franja[0])) / 60)
            : self::JORNADA_POR_DEFECTO;
        $limite = $ahora->subHours((int) ceil($jornadaHoras));

        $activos = Checkin::whereNull('hora_salida')->get(['id', 'hora_entrada']);
        $validos = $activos->filter(fn (Checkin $c) => $c->hora_entrada && $c->hora_entrada->gte($limite));

        $colgados = $activos->count() - $validos->count();
        if ($colgados > 0) {
            Log::warning("Tablero: {$colgados} check-in(s) llevan más de la jornada abiertos; no se cuentan en el %. Recepción debe revisarlos.");
        }

        $dentro = $validos->count();
        $pct = (int) min(100, round($dentro / max(1, (int) $espacio->capacidad) * 100));

        return [$dentro, $pct];
    }

    private function dentroDeFranja(CarbonImmutable $ahora, ?array $franja): bool
    {
        if ($franja === null) {
            return false;
        }
        $m = $ahora->hour * 60 + $ahora->minute;

        return $m >= $this->aMin($franja[0]) && $m < $this->aMin($franja[1]);
    }

    private function vigenteAhora(string $inicio, string $fin, CarbonImmutable $hoy, CarbonImmutable $ahora): bool
    {
        $ini = CarbonImmutable::parse($hoy->toDateString() . ' ' . substr($inicio, 0, 5));
        $end = CarbonImmutable::parse($hoy->toDateString() . ' ' . substr($fin, 0, 5));

        return $ini->lte($ahora) && $end->gt($ahora);
    }

    private function aMin(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hhmm, 0, 5)));

        return $h * 60 + $m;
    }
}
