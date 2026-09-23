<?php

namespace App\Http\Controllers\Api\Movil;

use App\Models\Plane;
use App\Servicios\Membresias\DescripcionDelPlan;
use App\Servicios\Pagos\CobroConTarjeta;
use Illuminate\Http\JsonResponse;

/**
 * Lo que la app consulta sin sesión (docs/API-MOVIL.md §6.12).
 */
class PublicoController extends ControladorMovil
{
    /** Al arrancar: versión mínima, mantenimiento y qué botones de acceso pintar. */
    public function estado(CobroConTarjeta $cobro): JsonResponse
    {
        return $this->datos([
            'version_minima'      => config('nodico.app_movil.version_minima'),
            'version_recomendada' => config('nodico.app_movil.version_recomendada'),
            'mantenimiento'       => [
                'activo'  => (bool) config('nodico.app_movil.mantenimiento'),
                'mensaje' => config('nodico.app_movil.mensaje_mantenimiento'),
            ],
            'acceso' => [
                'google'        => (bool) config('nodico.acceso.google') && config('nodico.app_movil.google_client_ids') !== [],
                'enlace_magico' => (bool) config('nodico.acceso.enlace_magico'),
            ],
            'pagos' => [
                'tarjeta'       => $cobro->disponible(),
                'llave_publica' => $cobro->disponible() ? config('cashier.key') : null,
                'referencia'    => true,
            ],
            // La web, para lo que la app abre en el navegador (registro,
            // contraseña olvidada, seguridad).
            'web' => url('/'),
        ]);
    }

    /** Vista previa de los planes para quien todavía no tiene cuenta. */
    public function planes(DescripcionDelPlan $descripcion): JsonResponse
    {
        return $this->datos(
            Plane::publicos()->get()->map(fn (Plane $plan) => $this->plan($descripcion, $plan))->values()
        );
    }

    private function plan(DescripcionDelPlan $descripcion, Plane $plan): array
    {
        $datos = $descripcion->para($plan);

        return [
            'id'               => $datos['id'],
            'nombre'           => $datos['nombre'],
            'subtitulo'        => $datos['subtitulo'],
            'precio'           => $datos['precio'],
            'periodo_etiqueta' => $datos['periodo_label'],
            'color'            => $datos['color'],
            'personas'         => $datos['personas'],
            'incluye'          => $datos['incluye'],
            'beneficios'       => $datos['beneficios'],
            'recurrente'       => (bool) $plan->cobro_recurrente,
        ];
    }
}
