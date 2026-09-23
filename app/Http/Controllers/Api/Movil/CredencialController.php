<?php

namespace App\Http\Controllers\Api\Movil;

use App\Http\Resources\Movil\UsuarioMovil;
use App\Models\User;
use App\Servicios\Accesos\Credenciales;
use App\Servicios\Membresias\MembresiaDelMiembro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La credencial QR (docs/API-MOVIL.md §6.9). La app la guarda y la enseña sin
 * conexión; recepción la valida contra el servidor al escanearla.
 */
class CredencialController extends ControladorMovil
{
    public function __construct(
        private readonly Credenciales $credenciales,
        private readonly MembresiaDelMiembro $membresias,
    ) {
    }

    public function mostrar(Request $request): JsonResponse
    {
        return $this->datos($this->paraLaApp($this->credenciales->vigente($request->user())));
    }

    /** Si se filtró una captura: código nuevo, el anterior deja de validar. */
    public function renovar(Request $request): JsonResponse
    {
        return $this->datos($this->paraLaApp($this->credenciales->emitir($request->user())));
    }

    private function paraLaApp(User $usuario): array
    {
        $suscripcion = $this->membresias->vigente($usuario);

        return [
            'nombre'        => $usuario->name,
            'avatar_url'    => UsuarioMovil::urlPublica($usuario->avatar),
            'plan'          => $suscripcion?->plan?->nombre,
            'color_plan'    => $suscripcion?->plan?->color,
            'vigente_hasta' => $suscripcion?->fecha_fin?->toDateString(),
            'face_id_ok'    => (bool) $usuario->face_id_ok,
            'codigo'        => $usuario->credencial_codigo,
            'emitida_en'    => $usuario->credencial_emitida_en?->toIso8601String(),
            'valida_hasta'  => $usuario->credencial_valida_hasta?->toIso8601String(),
        ];
    }
}
