<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * E — Derechos ARCO: acceso y portabilidad.
 *
 * La LFPDPPP reconoce el derecho de **acceso** a los datos propios. Entregarlo
 * como un archivo que la persona descarga desde su perfil, sin pedirselo a
 * nadie, es la forma mas directa de cumplirlo — y evita que cada solicitud
 * acabe siendo un correo a recepcion.
 *
 * Se entrega **solo lo suyo**, y se excluye a proposito `notas_admin`: son
 * apuntes internos del equipo, no datos que la persona proporciono.
 */
class DatosPersonalesController extends Controller
{
    public function descargar(Request $request): StreamedResponse
    {
        $usuario = $request->user();

        $datos = [
            'generado_en' => now()->toIso8601String(),
            'cuenta' => [
                'nombre'          => $usuario->name,
                'correo'          => $usuario->email,
                'telefono'        => $usuario->telefono,
                'empresa'         => $usuario->empresa,
                'ocupacion'       => $usuario->ocupacion,
                'rol'             => $usuario->rol?->value,
                'estado'          => $usuario->estado->value,
                'correo_verificado_en' => $usuario->email_verified_at?->toIso8601String(),
                'alta_en'         => $usuario->created_at?->toIso8601String(),
                'segundo_factor'  => $usuario->tieneDosFactores(),
            ],

            'consentimientos' => $usuario->consentimientos()
                ->orderBy('aceptado_en')
                ->get(['documento', 'version', 'aceptado_en', 'ip'])
                ->toArray(),

            'cuentas_externas' => $usuario->identidades()
                ->get(['proveedor', 'correo', 'created_at'])
                ->toArray(),

            'actividad_de_acceso' => $usuario->relationLoaded('eventos') ? [] :
                \App\Models\EventoAutenticacion::de($usuario)
                    ->recientes()
                    ->limit(200)
                    ->get(['tipo', 'exito', 'ip', 'created_at'])
                    ->toArray(),

            'suscripciones' => $usuario->suscripciones()->get()->toArray(),
            'reservas'      => $usuario->reservas()->get()->toArray(),
            'facturas'      => $usuario->facturas()->get()->toArray(),
            'comunicados'   => $usuario->comunicados()->get(['titulo', 'mensaje', 'created_at'])->toArray(),
        ];

        $nombre = 'nodico-mis-datos-' . now()->format('Y-m-d') . '.json';

        return response()->streamDownload(
            fn () => print(json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $nombre,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }
}
