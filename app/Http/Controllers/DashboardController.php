<?php

namespace App\Http\Controllers;

use App\Enums\EstadoAsesoria;
use App\Enums\EstadoRentaSalon;
use App\Models\Checkin;
use App\Models\Espacio;
use App\Models\RentaSalon;
use App\Models\Reserva;
use App\Models\SolicitudAsesoria;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Reservas\ValidadorDeReserva;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Tablero del día (Fase 3.1).
 *
 * Lo primero que ve recepción al abrir, y por eso el criterio no es «enseñar
 * datos» sino **responder a lo que alguien está a punto de preguntar en el
 * mostrador**: quién está dentro, qué sala se libera pronto, quién no ha hecho
 * check-in de su reserva, a quién se le vence la membresía esta semana.
 *
 * Los indicadores de arriba van después de las alertas a propósito: un número
 * bonito no es urgente, y esto se usa con alguien esperando enfrente.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly ValidadorDeReserva $calendario)
    {
    }

    public function index(Request $request)
    {
        $ahora = CarbonImmutable::now();
        $hoy   = $ahora->startOfDay();

        return Inertia::render('Dashboard', [
            'hoy' => $hoy->toDateString(),

            'dentroAhora' => Checkin::with(['user:id,name,empresa,avatar', 'espacio:id,nombre'])
                ->whereNull('hora_salida')
                ->latest('hora_entrada')
                ->get()
                ->map(fn (Checkin $c) => [
                    'id'      => $c->id,
                    'user_id' => $c->user_id,
                    'nombre'  => $c->user?->name,
                    'empresa' => $c->user?->empresa,
                    'espacio' => $c->espacio?->nombre,
                    'entrada' => $c->hora_entrada->toIso8601String(),
                    'minutos' => (int) $c->hora_entrada->diffInMinutes($ahora),
                ]),

            'lineaDelDia' => $this->lineaDelDia($hoy, $ahora),
            'alertas'     => $this->alertas($hoy, $ahora),

            'indicadores' => [
                'dentro_ahora'    => Checkin::whereNull('hora_salida')->count(),
                'reservas_hoy'    => Reserva::whereDate('fecha', $hoy)->confirmadas()->count(),
                'miembros_activos' => User::miembros()
                    ->whereHas('suscripciones', fn ($q) => $q->where('estatus', 'Activa')
                        ->whereDate('fecha_fin', '>=', $hoy))
                    ->count(),
                'asesorias_pendientes' => SolicitudAsesoria::pendientes()->count(),
                'eventos_proximos'     => RentaSalon::where('estado', EstadoRentaSalon::Confirmada->value)
                    ->whereDate('fecha', '>=', $hoy)->count(),
            ],
        ]);
    }

    /**
     * Búsqueda global de miembro: por nombre, correo o teléfono.
     *
     * Siempre a mano en la cabecera del panel. Recepción no busca por id ni
     * navega a un listado: alguien dice su nombre y hay que encontrarlo.
     */
    public function buscar(Request $request)
    {
        $texto = trim($request->string('q')->toString());

        if (mb_strlen($texto) < 2) {
            return response()->json(['resultados' => []]);
        }

        $like = '%' . $texto . '%';

        // El teléfono se busca también sin separadores: la gente lo dicta como
        // «999 461 5676» y en la base puede estar de cualquier forma.
        $soloDigitos = preg_replace('/\D/', '', $texto);

        $miembros = User::miembros()
            ->where(fn ($q) => $q
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('empresa', 'like', $like)
                ->orWhere('telefono', 'like', $like)
                ->when($soloDigitos !== '', fn ($q2) => $q2->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '+', '') LIKE ?",
                    ['%' . $soloDigitos . '%'],
                )))
            ->with(['suscripcionActiva.plan:id,nombre'])
            ->limit(8)
            ->get(['id', 'name', 'email', 'telefono', 'empresa', 'face_id_ok']);

        return response()->json([
            'resultados' => $miembros->map(fn (User $u) => [
                'id'         => $u->id,
                'nombre'     => $u->name,
                'email'      => $u->email,
                'telefono'   => $u->telefono,
                'empresa'    => $u->empresa,
                'plan'       => $u->suscripcionActiva?->plan?->nombre,
                'face_id_ok' => (bool) $u->face_id_ok,
                'url'        => route('miembros.show', $u),
            ]),
        ]);
    }

    /**
     * Reservas de hoy agrupadas por espacio, en forma de línea de tiempo.
     *
     * Es lo que permite ver de golpe qué sala está ocupada, cuál se libera
     * pronto y cuál está vacía. Los porcentajes se calculan en el servidor
     * porque dependen del horario de cada espacio, que ya sabe el validador.
     */
    private function lineaDelDia(CarbonImmutable $hoy, CarbonImmutable $ahora): array
    {
        $espacios = Espacio::reservables()->orderBy('tipo')->orderBy('nombre')->get();

        $reservas = Reserva::with('user:id,name')
            ->whereDate('fecha', $hoy)
            ->confirmadas()
            ->orderBy('hora_inicio')
            ->get()
            ->groupBy('espacio_id');

        $bloqueos = \App\Models\BloqueoEspacio::whereDate('fecha', $hoy)->get()->groupBy('espacio_id');

        return $espacios->map(function (Espacio $espacio) use ($reservas, $bloqueos, $hoy, $ahora) {
            $franja = $this->calendario->franjaDelDia($espacio, $hoy);

            if ($franja === null) {
                return [
                    'espacio_id' => $espacio->id,
                    'nombre'     => $espacio->nombre,
                    'tipo'       => $espacio->tipo_label,
                    'abierto'    => false,
                    'tramos'     => [],
                    'ocupado_ahora' => null,
                    'se_libera'  => null,
                ];
            }

            [$apertura, $cierre] = $franja;
            $inicioMin = Reserva::aMinutos($apertura);
            $finMin    = Reserva::aMinutos($cierre);
            $ancho     = max(1, $finMin - $inicioMin);

            $tramos = collect();

            foreach ($reservas->get($espacio->id, collect()) as $r) {
                $tramos->push($this->tramo(
                    $r->hora_inicio, $r->hora_fin, $inicioMin, $ancho,
                    'reserva', $r->user?->name ?? 'Miembro', $r->id,
                ));
            }

            foreach ($bloqueos->get($espacio->id, collect()) as $b) {
                $tramos->push($this->tramo(
                    $b->hora_inicio, $b->hora_fin, $inicioMin, $ancho,
                    'bloqueo', $b->motivo, $b->id,
                ));
            }

            // Qué pasa ahora mismo y cuándo se libera: la pregunta de mostrador.
            $ocupadoAhora = null;
            $seLibera     = null;

            foreach ($reservas->get($espacio->id, collect()) as $r) {
                if ($r->inicioEnCalendario()->lte($ahora) && $r->finEnCalendario()->gt($ahora)) {
                    $ocupadoAhora = $r->user?->name ?? 'Miembro';
                    $seLibera     = substr($r->hora_fin, 0, 5);
                    break;
                }
            }

            return [
                'espacio_id'    => $espacio->id,
                'nombre'        => $espacio->nombre,
                'tipo'          => $espacio->tipo_label,
                'abierto'       => true,
                'apertura'      => $apertura,
                'cierre'        => $cierre,
                'tramos'        => $tramos->sortBy('izquierda')->values(),
                'ocupado_ahora' => $ocupadoAhora,
                'se_libera'     => $seLibera,
                // Posición de la línea de «ahora», o null si el día no está en curso.
                'ahora_pct'     => $this->posicionDeAhora($ahora, $inicioMin, $ancho),
            ];
        })->values()->all();
    }

    private function tramo(
        string $inicio,
        string $fin,
        int $inicioMin,
        int $ancho,
        string $tipo,
        string $etiqueta,
        int $id,
    ): array {
        $desde = Reserva::aMinutos($inicio);
        $hasta = Reserva::aMinutos($fin);

        return [
            'id'        => $tipo . '-' . $id,
            'tipo'      => $tipo,
            'etiqueta'  => $etiqueta,
            'inicio'    => substr($inicio, 0, 5),
            'fin'       => substr($fin, 0, 5),
            'izquierda' => round(max(0, $desde - $inicioMin) / $ancho * 100, 2),
            'ancho'     => round(min($ancho, $hasta - max($desde, $inicioMin)) / $ancho * 100, 2),
        ];
    }

    private function posicionDeAhora(CarbonImmutable $ahora, int $inicioMin, int $ancho): ?float
    {
        $minutos = $ahora->hour * 60 + $ahora->minute;

        if ($minutos < $inicioMin || $minutos > $inicioMin + $ancho) {
            return null;
        }

        return round(($minutos - $inicioMin) / $ancho * 100, 2);
    }

    /**
     * Lo que exige acción. Ordenado por urgencia real, no por tipo: recepción
     * atiende de arriba abajo y lo de arriba tiene que ser lo que más corre.
     */
    private function alertas(CarbonImmutable $hoy, CarbonImmutable $ahora): array
    {
        $alertas = [];

        // 1 · Reservas que empiezan pronto y nadie ha llegado.
        $porEmpezar = Reserva::with(['user:id,name', 'espacio:id,nombre'])
            ->whereDate('fecha', $hoy)
            ->confirmadas()
            ->doesntHave('checkin')
            ->get()
            ->filter(fn (Reserva $r) => $r->inicioEnCalendario()->between($ahora->subMinutes(30), $ahora->addMinutes(30)))
            ->map(fn (Reserva $r) => [
                'texto'  => ($r->user?->name ?? 'Miembro') . ' · ' . ($r->espacio?->nombre ?? '')
                    . ' a las ' . substr($r->hora_inicio, 0, 5),
                'url'    => $r->user ? route('miembros.show', $r->user_id) : null,
            ])
            ->values();

        if ($porEmpezar->isNotEmpty()) {
            $alertas[] = [
                'clave'  => 'sin_checkin',
                'tono'   => 'atencion',
                'titulo' => $porEmpezar->count() === 1
                    ? 'Una reserva empieza y no ha llegado'
                    : $porEmpezar->count() . ' reservas empiezan y no han llegado',
                'items'  => $porEmpezar,
            ];
        }

        // 2 · Solicitudes de asesoría sin responder.
        $asesorias = SolicitudAsesoria::with('user:id,name')
            ->pendientes()
            ->orderBy('dia_preferido')
            ->limit(6)
            ->get()
            ->map(fn (SolicitudAsesoria $s) => [
                'texto' => ($s->user?->name ?? 'Miembro') . ' · para el '
                    . $s->dia_preferido->translatedFormat('j \d\e F'),
                'url'   => route('asesorias.index'),
            ]);

        if ($asesorias->isNotEmpty()) {
            $alertas[] = [
                'clave'  => 'asesorias',
                'tono'   => 'atencion',
                'titulo' => SolicitudAsesoria::pendientes()->count() . ' solicitud(es) de asesoría sin responder',
                'items'  => $asesorias,
            ];
        }

        // 3 · Membresías que vencen esta semana: llamar antes, no después.
        $porVencer = Suscripcion::with(['user:id,name,telefono', 'plan:id,nombre'])
            ->where('estatus', 'Activa')
            ->whereDate('fecha_fin', '>=', $hoy)
            ->whereDate('fecha_fin', '<=', $hoy->addDays(7))
            ->orderBy('fecha_fin')
            ->get()
            ->map(fn (Suscripcion $s) => [
                'texto' => ($s->user?->name ?? '') . ' · ' . ($s->plan?->nombre ?? '')
                    . ' vence el ' . $s->fecha_fin->translatedFormat('j \d\e F'),
                'url'   => route('miembros.show', $s->user_id),
            ]);

        if ($porVencer->isNotEmpty()) {
            $alertas[] = [
                'clave'  => 'por_vencer',
                'tono'   => 'atencion',
                'titulo' => $porVencer->count() . ' membresía(s) vencen esta semana',
                'items'  => $porVencer,
            ];
        }

        // 4 · Miembros activos sin Face ID: no pueden entrar solos.
        $sinFaceId = User::miembros()
            ->where('face_id_ok', false)
            ->whereHas('suscripciones', fn ($q) => $q->where('estatus', 'Activa')
                ->whereDate('fecha_fin', '>=', $hoy))
            ->limit(6)
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['texto' => $u->name, 'url' => route('miembros.show', $u)]);

        if ($sinFaceId->isNotEmpty()) {
            $alertas[] = [
                'clave'  => 'sin_faceid',
                'tono'   => 'neutro',
                'titulo' => 'Miembros activos sin Face ID',
                'items'  => $sinFaceId,
            ];
        }

        return $alertas;
    }
}
