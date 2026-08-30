<?php

namespace App\Http\Controllers;

use App\Enums\EstadoRentaSalon;
use App\Enums\TipoEspacio;
use App\Enums\TipoMontaje;
use App\Models\BloqueoEspacio;
use App\Models\Contacto;
use App\Models\Espacio;
use App\Models\RentaSalon;
use App\Servicios\Reservas\RegistroDeBloques;
use App\Servicios\Salones\CotizadorDeSalon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Salones Yucatán Emprende (Fase 3.5).
 *
 * No son salas de miembro: se cotizan por hora, llevan coffee break y los
 * contrata gente de fuera. La pantalla es un cotizador antes que un formulario,
 * porque lo que hace recepción con un salón es **decir cuánto cuesta**, y ahí
 * es donde se equivoca la gente al hacer cuentas a mano.
 *
 * Una cotización no aparta la fecha; una renta confirmada sí, y para eso genera
 * un bloqueo en la misma agenda que todo lo demás.
 */
class SalonesController extends Controller
{
    public function __construct(
        private readonly CotizadorDeSalon $cotizador,
        private readonly RegistroDeBloques $bloques,
    ) {
    }

    public function index(Request $request)
    {
        $rentas = RentaSalon::with(['espacio:id,nombre', 'creadoPor:id,name'])
            ->when($request->string('estado')->toString(), fn ($q, $e) => $q->where('estado', $e))
            ->when($request->string('buscar')->toString(), function ($q, $texto) {
                $like = '%' . $texto . '%';
                $q->where(fn ($s) => $s
                    ->where('cliente_nombre', 'like', $like)
                    ->orWhere('cliente_empresa', 'like', $like)
                    ->orWhere('evento_nombre', 'like', $like)
                    ->orWhere('cliente_email', 'like', $like));
            })
            ->orderByRaw('fecha IS NULL, fecha DESC')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (RentaSalon $r) => [
                'id'         => $r->id,
                'espacio_id' => $r->espacio_id,
                'cliente'   => $r->cliente_nombre,
                'empresa'   => $r->cliente_empresa,
                'evento'    => $r->evento_nombre,
                'salon'     => $r->espacio?->nombre,
                'fecha'     => $r->fecha?->toDateString(),
                'inicio'    => $r->hora_inicio ? substr($r->hora_inicio, 0, 5) : null,
                'fin'       => $r->hora_fin ? substr($r->hora_fin, 0, 5) : null,
                'personas'  => $r->personas,
                'montaje'   => $r->montaje?->etiqueta(),
                'total'     => (float) $r->total,
                'anticipo'  => (float) $r->anticipo,
                'saldo'     => $r->saldoPendiente(),
                'anticipo_pagado' => $r->anticipoPagado(),
                'estado'    => $r->estado->value,
                'estado_etiqueta' => $r->estado->etiqueta(),
                'tono'      => $r->estado->tono(),
            ]);

        return Inertia::render('Salones/Index', [
            'rentas'  => $rentas,
            'filtros' => $request->only(['estado', 'buscar']),
            'estados' => collect(EstadoRentaSalon::cases())
                ->map(fn ($e) => ['valor' => $e->value, 'etiqueta' => $e->etiqueta()]),
            'salones' => $this->salones(),
            'montajes' => $this->montajes(),
            'tarifas' => [
                'precio_hora' => (float) config('nodico.salones.precio_hora'),
                'coffee'      => config('nodico.salones.coffee'),
            ],

            // Prospectos del formulario «Hablemos» que todavía nadie atendió:
            // ahí es donde llegan las peticiones de salón del sitio público.
            'prospectos' => Contacto::where('atendido', false)
                ->latest()
                ->limit(10)
                ->get(['id', 'nombre', 'email', 'telefono', 'empresa', 'asunto', 'comentarios', 'created_at']),
        ]);
    }

    /** Cotiza en vivo mientras recepción escribe. Mismo cálculo que al guardar. */
    public function cotizar(Request $request)
    {
        $datos = $request->validate([
            'espacio_id'      => ['nullable', 'exists:espacios,id'],
            'horas'           => ['nullable', 'numeric', 'min:0', 'max:24'],
            'con_coffee_break' => ['boolean'],
            'coffee_personas' => ['nullable', 'integer', 'min:0', 'max:500'],
            'coffee_precio_persona' => ['nullable', 'numeric', 'min:0'],
            'descuento'       => ['nullable', 'numeric', 'min:0'],
            'montaje'         => ['nullable', Rule::enum(TipoMontaje::class)],
            'personas'        => ['nullable', 'integer', 'min:0'],
        ]);

        $salon   = isset($datos['espacio_id']) ? Espacio::find($datos['espacio_id']) : null;
        $montaje = isset($datos['montaje']) ? TipoMontaje::from($datos['montaje']) : null;

        return response()->json([
            ...$this->cotizador->cotizar(
                salon: $salon,
                horas: (float) ($datos['horas'] ?? 0),
                conCoffee: (bool) ($datos['con_coffee_break'] ?? false),
                coffeePersonas: (int) ($datos['coffee_personas'] ?? 0),
                precioCoffeePersona: isset($datos['coffee_precio_persona'])
                    ? (float) $datos['coffee_precio_persona'] : null,
                descuento: (float) ($datos['descuento'] ?? 0),
            ),
            'avisos_aforo' => $this->cotizador->avisosDeAforo($salon, $montaje, (int) ($datos['personas'] ?? 0)),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        $renta = DB::transaction(function () use ($datos, $request) {
            $renta = RentaSalon::create([
                ...$this->conImportes($datos),
                'creado_por_user_id' => $request->user()->id,
            ]);

            if ($renta->estado->ocupaAgenda()) {
                $this->apartarFecha($renta, $request);
            }

            return $renta;
        });

        return redirect()
            ->route('salones.index')
            ->with('success', $renta->estado === EstadoRentaSalon::Confirmada
                ? 'Renta confirmada y fecha apartada en la agenda.'
                : 'Cotización guardada. La fecha no queda apartada hasta confirmarla.');
    }

    public function update(Request $request, RentaSalon $salon)
    {
        $datos = $this->validar($request);

        DB::transaction(function () use ($salon, $datos, $request) {
            $ocupabaAntes = $salon->estado->ocupaAgenda();

            $salon->update($this->conImportes($datos));
            $salon->refresh();

            $ocupaAhora = $salon->estado->ocupaAgenda();

            // El bloqueo sigue al estado y a la fecha: si cambia cualquiera de
            // los dos, se rehace. Dejarlo colgado apartaría una fecha que ya
            // nadie va a usar.
            if ($ocupabaAntes) {
                $this->soltarFecha($salon);
            }

            if ($ocupaAhora) {
                $this->apartarFecha($salon, $request);
            }
        });

        return back()->with('success', 'Renta actualizada.');
    }

    public function destroy(RentaSalon $salon)
    {
        DB::transaction(function () use ($salon) {
            $this->soltarFecha($salon);
            $salon->update(['estado' => EstadoRentaSalon::Cancelada->value]);
        });

        return back()->with('success', 'Renta cancelada y fecha liberada.');
    }

    /** Marca el anticipo como cobrado. El monto lo escribe recepción. */
    public function anticipo(Request $request, RentaSalon $salon)
    {
        $datos = $request->validate([
            'anticipo'           => ['required', 'numeric', 'min:0'],
            'anticipo_pagado_el' => ['nullable', 'date'],
        ]);

        if ((float) $datos['anticipo'] > (float) $salon->total) {
            return back()->withErrors([
                'anticipo' => 'El anticipo no puede pasar del total de la renta.',
            ]);
        }

        $salon->update([
            'anticipo'           => $datos['anticipo'],
            'anticipo_pagado_el' => $datos['anticipo_pagado_el'] ?? today()->toDateString(),
        ]);

        return back()->with('success', 'Anticipo registrado.');
    }

    // ── Apoyo ───────────────────────────────────────────────────────────────

    private function validar(Request $request): array
    {
        return $request->validate([
            'espacio_id'       => ['required', 'exists:espacios,id'],
            'cliente_nombre'   => ['required', 'string', 'max:150'],
            'cliente_email'    => ['nullable', 'email', 'max:150'],
            'cliente_telefono' => ['nullable', 'string', 'max:30'],
            'cliente_empresa'  => ['nullable', 'string', 'max:150'],
            'user_id'          => ['nullable', 'exists:users,id'],
            'contacto_id'      => ['nullable', 'exists:contactos,id'],

            'evento_nombre'    => ['nullable', 'string', 'max:200'],
            'fecha'            => ['nullable', 'date'],
            'hora_inicio'      => ['nullable', 'date_format:H:i'],
            'hora_fin'         => ['nullable', 'date_format:H:i', 'after:hora_inicio'],

            'montaje'          => ['nullable', Rule::enum(TipoMontaje::class)],
            'personas'         => ['nullable', 'integer', 'min:0', 'max:500'],

            'con_coffee_break' => ['boolean'],
            'coffee_personas'  => ['nullable', 'integer', 'min:0', 'max:500'],
            'coffee_precio_persona' => ['nullable', 'numeric', 'min:0'],
            'descuento'        => ['nullable', 'numeric', 'min:0'],

            'anticipo'         => ['nullable', 'numeric', 'min:0'],
            'estado'           => ['required', Rule::enum(EstadoRentaSalon::class)],
            'notas'            => ['nullable', 'string', 'max:1000'],
        ], [
            'cliente_nombre.required' => 'Hace falta a nombre de quién va la renta.',
        ]);
    }

    /**
     * Añade los importes calculados. **El total nunca llega del formulario**:
     * se recalcula aquí, porque un total que viaja por el navegador es un total
     * que se puede editar.
     */
    private function conImportes(array $datos): array
    {
        $salon = Espacio::find($datos['espacio_id']);

        $horas = ! empty($datos['hora_inicio']) && ! empty($datos['hora_fin'])
            ? \App\Models\Reserva::calcularHoras($datos['hora_inicio'], $datos['hora_fin'])
            : 0;

        $cotizacion = $this->cotizador->cotizar(
            salon: $salon,
            horas: $horas,
            conCoffee: (bool) ($datos['con_coffee_break'] ?? false),
            coffeePersonas: (int) ($datos['coffee_personas'] ?? 0),
            precioCoffeePersona: isset($datos['coffee_precio_persona'])
                ? (float) $datos['coffee_precio_persona'] : null,
            descuento: (float) ($datos['descuento'] ?? 0),
        );

        if ($cotizacion['coffee_requiere_precio']) {
            throw ValidationException::withMessages([
                'coffee_precio_persona' => $cotizacion['coffee_nota'],
            ]);
        }

        return [
            ...$datos,
            'precio_hora'           => $cotizacion['precio_hora'],
            'horas'                 => $cotizacion['horas'],
            'subtotal_salon'        => $cotizacion['subtotal_salon'],
            'coffee_precio_persona' => $cotizacion['coffee_precio_persona'],
            'subtotal_coffee'       => $cotizacion['subtotal_coffee'],
            'total'                 => $cotizacion['total'],
        ];
    }

    /** Confirmar una renta aparta la fecha con un bloqueo en la agenda común. */
    private function apartarFecha(RentaSalon $renta, Request $request): void
    {
        if (! $renta->fecha || ! $renta->hora_inicio || ! $renta->hora_fin) {
            throw ValidationException::withMessages([
                'fecha' => 'Para confirmar hacen falta fecha y horario: es lo que aparta el salón.',
            ]);
        }

        $bloqueo = BloqueoEspacio::create([
            'espacio_id'  => $renta->espacio_id,
            'fecha'       => $renta->fecha->toDateString(),
            'hora_inicio' => $renta->hora_inicio,
            'hora_fin'    => $renta->hora_fin,
            'motivo'      => 'Evento: ' . ($renta->evento_nombre ?: $renta->cliente_nombre),
            'notas'       => 'Renta de salón #' . $renta->id,
            'creado_por_user_id' => $request->user()->id,
        ]);

        try {
            $this->bloques->ocuparBloqueo($bloqueo);
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'fecha' => 'Ese salón ya está apartado en ese horario.',
            ]);
        }

        $renta->update(['bloqueo_id' => $bloqueo->id]);
    }

    private function soltarFecha(RentaSalon $renta): void
    {
        if (! $renta->bloqueo) {
            return;
        }

        $this->bloques->liberarBloqueo($renta->bloqueo);
        $renta->bloqueo->delete();
        $renta->update(['bloqueo_id' => null]);
    }

    private function salones()
    {
        return Espacio::where('tipo', TipoEspacio::SalonEventos->value)
            ->orderBy('orden')
            ->get()
            ->map(fn (Espacio $e) => [
                'id'          => $e->id,
                'nombre'      => $e->nombre,
                'capacidad'   => $e->capacidad,
                'precio_hora' => (float) $e->precio_hora,
                'medidas'     => $e->medidas,
                'capacidades' => collect(TipoMontaje::cases())
                    ->mapWithKeys(fn (TipoMontaje $m) => [$m->value => $m->capacidadEn($e)]),
            ]);
    }

    private function montajes()
    {
        return collect(TipoMontaje::cases())->map(fn (TipoMontaje $m) => [
            'valor'       => $m->value,
            'etiqueta'    => $m->etiqueta(),
            'descripcion' => $m->descripcion(),
        ]);
    }
}
