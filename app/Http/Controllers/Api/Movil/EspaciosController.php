<?php

namespace App\Http\Controllers\Api\Movil;

use App\Exceptions\ErrorDeApi;
use App\Http\Resources\Movil\UsuarioMovil;
use App\Models\Espacio;
use App\Models\Plane;
use App\Models\Suscripcion;
use App\Models\User;
use App\Servicios\Membresias\MembresiaDelMiembro;
use App\Servicios\Reservas\Disponibilidad;
use App\Servicios\Reservas\ServicioDeReservas;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espacios y su disponibilidad (docs/API-MOVIL.md §6.3): lo que alimenta el
 * flujo de reservar — elegir espacio, día en el calendario horizontal y hueco
 * en la franja del día.
 */
class EspaciosController extends ControladorMovil
{
    /** Máximo de días por llamada al resumen del calendario. */
    private const DIAS_POR_LLAMADA = 45;

    public function __construct(
        private readonly MembresiaDelMiembro $membresias,
        private readonly ServicioDeReservas $reservas,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $suscripcion = $this->membresia($request->user());

        return $this->datos([
            'espacios' => $this->reservas->espaciosPara($suscripcion)->map(fn (Espacio $espacio) => [
                'id'             => $espacio->id,
                'nombre'         => $espacio->nombre,
                'tipo'           => $espacio->tipo,
                'tipo_etiqueta'  => $espacio->tipo_label,
                'capacidad'      => $espacio->capacidad,
                'descripcion'    => $espacio->descripcion,
                'imagen_url'     => UsuarioMovil::urlPublica($espacio->imagen),
                'amenidades'     => $espacio->amenidades ?? [],
                'bolsa'          => $espacio->bolsa()->value,
                'bolsa_etiqueta' => $espacio->bolsa()->etiqueta(),
            ])->values(),
            'horizonte' => $this->reservas->horizonte($suscripcion),
            'operacion' => [
                'granularidad_minutos' => (int) config('nodico.operacion.granularidad_minutos', 60),
                'horas_para_cancelar_sin_penalizacion' => (int) config('nodico.operacion.horas_para_cancelar_sin_penalizacion'),
            ],
        ]);
    }

    /** Resumen por día para el calendario: cuántos huecos libres tiene cada uno. */
    public function periodo(Request $request, int $espacio, Disponibilidad $disponibilidad): JsonResponse
    {
        $datos = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $suscripcion = $this->membresia($request->user());
        $modelo      = $this->espacioReservable($espacio, $suscripcion);

        $hoy   = \App\Models\Reserva::hoy();
        $tope  = $this->reservas->ultimoDiaReservable($suscripcion);
        $desde = isset($datos['desde']) ? CarbonImmutable::parse($datos['desde'])->max($hoy) : $hoy;
        $hasta = isset($datos['hasta'])
            ? CarbonImmutable::parse($datos['hasta'])
            : $desde->addDays(self::DIAS_POR_LLAMADA - 1);
        $hasta = $hasta->min($tope)->min($desde->addDays(self::DIAS_POR_LLAMADA - 1));

        return $this->datos($hasta->lt($desde) ? [] : $disponibilidad->delPeriodo($modelo, $desde, $hasta));
    }

    /** La franja de un día más los números para validar en vivo. */
    public function dia(Request $request, int $espacio, string $fecha): JsonResponse
    {
        $usuario     = $request->user();
        $suscripcion = $this->membresia($usuario);
        $modelo      = $this->espacioReservable($espacio, $suscripcion);

        return $this->datos($this->reservas->delDia($usuario, $suscripcion, $modelo, CarbonImmutable::parse($fecha)));
    }

    /** @throws ErrorDeApi */
    private function membresia(User $usuario): Suscripcion
    {
        $suscripcion = $this->membresias->vigente($usuario);

        if (! $suscripcion) {
            throw new ErrorDeApi(403, 'sin_membresia', 'Necesitas una membresía activa para reservar.');
        }

        return $suscripcion;
    }

    /** @throws ErrorDeApi */
    private function espacioReservable(int $id, Suscripcion $suscripcion): Espacio
    {
        $espacio = Espacio::find($id);

        abort_if(! $espacio || ! $espacio->esReservablePorMiembro(), 404);

        $bolsa = $espacio->bolsa();

        if (! $bolsa?->incluidaEn($suscripcion->plan)) {
            $mejor = Plane::query()->where('activo', true)->whereNotNull($bolsa->campoCupo())->orderBy('precio')->first();

            throw new ErrorDeApi(403, 'plan_no_incluye', "Tu plan {$suscripcion->plan?->nombre} no incluye {$bolsa->etiqueta()}.", [
                'sugerencia' => $mejor ? [
                    'plan_id' => $mejor->id,
                    'plan'    => $mejor->nombre,
                    'precio'  => (float) $mejor->precio,
                    'incluye' => $bolsa->cupo($mejor),
                ] : null,
            ]);
        }

        return $espacio;
    }
}
