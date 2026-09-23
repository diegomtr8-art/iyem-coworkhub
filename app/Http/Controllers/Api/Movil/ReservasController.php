<?php

namespace App\Http\Controllers\Api\Movil;

use App\Exceptions\ErrorDeApi;
use App\Http\Resources\Movil\ReservaMovil;
use App\Models\Reserva;
use App\Servicios\Horas\LibroDeHoras;
use App\Servicios\Membresias\MembresiaDelMiembro;
use App\Servicios\Reservas\ServicioDeReservas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reservas (docs/API-MOVIL.md §6.4). Las reglas son las del portal web, porque
 * son el mismo código: `ServicioDeReservas`.
 *
 * Solo las **propias**. En un Match, titular y acompañante comparten la bolsa,
 * pero ninguno ve ni cancela las reservas del otro (decisión del 22/09/2026).
 */
class ReservasController extends ControladorMovil
{
    public function __construct(private readonly ServicioDeReservas $reservas)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => ['nullable', Rule::in(['proximas', 'pasadas'])],
        ]);

        $usuario = $request->user();

        if (($datos['tipo'] ?? 'proximas') === 'proximas') {
            return $this->datos(
                $usuario->reservas()
                    ->with('espacio')
                    ->where('fecha', '>=', \App\Models\Reserva::hoyYmd())
                    ->confirmadas()
                    ->orderBy('fecha')
                    ->orderBy('hora_inicio')
                    ->get()
                    ->map(fn (Reserva $r) => (new ReservaMovil($r))->resolve())
                    ->values()
            );
        }

        $pagina = $usuario->reservas()
            ->with('espacio')
            ->where(fn ($q) => $q->where('fecha', '<', \App\Models\Reserva::hoyYmd())
                ->orWhereIn('estatus', ['Cancelada', 'Completada', 'No_Show']))
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->orderByDesc('id')
            ->cursorPaginate(20);

        return $this->pagina($pagina, fn (Reserva $r) => (new ReservaMovil($r))->resolve());
    }

    public function mostrar(Request $request, int $id): JsonResponse
    {
        return $this->datos(new ReservaMovil($this->propia($request, $id)));
    }

    public function crear(Request $request, LibroDeHoras $libro): JsonResponse
    {
        $datos = $request->validate([
            'espacio_id'  => ['required', 'integer', 'exists:espacios,id'],
            'fecha'       => ['required', 'date_format:Y-m-d', 'after_or_equal:' . \App\Models\Reserva::hoyYmd()],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin'    => ['required', 'date_format:H:i', 'after:hora_inicio'],
        ]);

        if (! app(MembresiaDelMiembro::class)->vigente($request->user())) {
            throw new ErrorDeApi(403, 'sin_membresia', 'Necesitas una membresía activa para reservar.');
        }

        $reserva = $this->reservas->reservar($request->user(), $datos)->load('espacio', 'suscripcion.plan');
        $bolsa   = $reserva->bolsa();

        return $this->datos([
            'reserva' => (new ReservaMovil($reserva))->resolve(),

            // Para que la app pinte el anillo actualizado sin otra llamada.
            'bolsa_despues' => $bolsa ? [
                'bolsa'    => $bolsa->value,
                'usado'    => $libro->consumoDelCiclo($reserva->suscripcion, $bolsa),
                'restante' => $libro->saldoDelCiclo($reserva->suscripcion, $bolsa),
            ] : null,

            'message' => '¡Reserva confirmada! Te esperamos.',
        ], 201);
    }

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $devolvio = $this->reservas->cancelar($this->propia($request, $id));

        if ($devolvio === null) {
            throw new ErrorDeApi(409, 'ya_no_confirmada', 'Esa reserva ya no estaba confirmada.');
        }

        return $this->datos([
            'devolvio_horas' => $devolvio,
            'message'        => $this->reservas->mensajeDeCancelacion($devolvio),
        ]);
    }

    /** 404 si no existe **o no es tuya**: un 403 confirmaría que existe. */
    private function propia(Request $request, int $id): Reserva
    {
        $reserva = $request->user()->reservas()->with('espacio')->whereKey($id)->first();

        abort_unless($reserva, 404);

        return $reserva;
    }
}
