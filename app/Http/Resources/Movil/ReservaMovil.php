<?php

namespace App\Http\Resources\Movil;

use App\Models\Reserva;
use App\Servicios\Reservas\ServicioDeReservas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una reserva, con lo que la app necesita saber **antes** de pulsar cancelar:
 * si devuelve las horas y hasta cuándo.
 *
 * @mixin Reserva
 */
class ReservaMovil extends JsonResource
{
    public function toArray(Request $request): array
    {
        $bolsa = $this->bolsa();

        return [
            'id'          => $this->id,
            'estatus'     => $this->estatus,
            'espacio'     => $this->espacio ? [
                'id'     => $this->espacio->id,
                'nombre' => $this->espacio->nombre,
                'tipo'   => $this->espacio->tipo,
            ] : null,
            'fecha'       => $this->fecha->toDateString(),
            'hora_inicio' => substr((string) $this->hora_inicio, 0, 5),
            'hora_fin'    => substr((string) $this->hora_fin, 0, 5),
            'horas'       => $this->duracionEnHoras(),
            'bolsa'          => $bolsa?->value,
            'bolsa_etiqueta' => $bolsa?->etiqueta(),
            'empieza_en'  => $this->inicioEnCalendario()->toIso8601String(),
            'termina_en'  => $this->finEnCalendario()->toIso8601String(),
            'cancelar_devuelve'  => $this->estaConfirmada() && $this->cancelarDevuelveHoras(),
            'limite_cancelacion' => app(ServicioDeReservas::class)->limiteDeCancelacion($this->resource),
        ];
    }
}
