<?php

namespace App\Http\Controllers\Portal;

use App\Enums\AccionOperativa;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarDatosFiscalesRequest;
use App\Models\DatosFiscales;
use App\Models\EntradaBitacora;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Datos fiscales del miembro (Fases 1.3 y 2.3).
 *
 * Dos cosas que esta pantalla **tiene que decir**, y que el sistema no puede
 * dar por sabidas:
 *
 * 1. **Nódico no emite la factura.** La emite contabilidad del IYEM. Un miembro
 *    que llena estos campos y espera un PDF al instante va a escribir a
 *    recepción en veinte minutos.
 * 2. **Cuánto tarda.** Sin plazo, cualquier espera se siente como un olvido.
 *
 * Todo acceso queda en la bitácora, incluida la simple lectura: es un dato
 * personal sensible y el registro es parte de tratarlo como tal.
 */
class DatosFiscalesController extends Controller
{
    public function edit(Request $request)
    {
        $usuario = $request->user();
        $datos   = $usuario->datosFiscales;

        if ($datos) {
            $this->registrarConsulta($request, $datos);
        }

        return Inertia::render('Portal/DatosFiscales', [
            'datos' => $datos?->only([
                'rfc', 'razon_social', 'regimen_fiscal', 'uso_cfdi',
                'codigo_postal', 'email_facturacion',
            ]),

            'completos' => (bool) $datos?->estanCompletos(),

            'actualizados_el' => $datos?->updated_at?->toIso8601String(),

            // Catálogos del SAT, tal cual. Ver `config/sat.php`.
            'regimenes' => collect(config('sat.regimenes'))
                ->map(fn (array $r, string $clave) => [
                    'clave'    => $clave,
                    'nombre'   => $r['nombre'],
                    'personas' => $r['personas'],
                ])
                ->values(),

            'usosCfdi' => collect(config('sat.usos_cfdi'))
                ->map(fn (string $nombre, string $clave) => ['clave' => $clave, 'nombre' => $nombre])
                ->values(),

            'usoPredeterminado' => config('sat.uso_cfdi_predeterminado'),

            'proceso' => [
                'emisor'        => 'Contabilidad del Instituto Yucateco de Emprendedores',
                'dias_habiles'  => (int) config('sat.dias_habiles_emision'),
                'contacto'      => config('sat.contacto_facturacion'),
            ],

            'correoCuenta' => $usuario->email,
        ]);
    }

    public function update(GuardarDatosFiscalesRequest $request)
    {
        $usuario = $request->user();
        $datos   = $usuario->datosFiscales;
        $esAlta  = $datos === null;

        $antes = $datos?->only(['rfc', 'razon_social', 'regimen_fiscal', 'uso_cfdi', 'codigo_postal', 'email_facturacion']);

        $datos = DatosFiscales::updateOrCreate(
            ['user_id' => $usuario->id],
            [...$request->validated(), 'actualizado_por_user_id' => $usuario->id],
        );

        EntradaBitacora::registrar(
            accion: AccionOperativa::CambioDatosFiscales,
            descripcion: $esAlta
                ? 'Alta de datos fiscales por el propio miembro.'
                : 'Cambio de datos fiscales por el propio miembro.',
            actor: $usuario,
            sujeto: $usuario,
            contexto: ['antes' => $antes, 'despues' => $datos->only(array_keys($request->validated()))],
        );

        return back()->with('success', $esAlta
            ? 'Datos fiscales guardados. Ya puedes pedir factura.'
            : 'Datos fiscales actualizados.');
    }

    /**
     * Se registra la lectura, no solo el cambio.
     *
     * Se agrupa por hora: entrar tres veces a la pantalla en una sesión no tiene
     * por qué generar tres entradas, y una bitácora que crece por navegar es una
     * bitácora que nadie lee.
     */
    private function registrarConsulta(Request $request, DatosFiscales $datos): void
    {
        $usuario = $request->user();

        $yaRegistrada = EntradaBitacora::query()
            ->where('actor_user_id', $usuario->id)
            ->where('sujeto_user_id', $usuario->id)
            ->deAccion(AccionOperativa::ConsultaDatosFiscales)
            ->where('created_at', '>=', now()->subHour())
            ->exists();

        if ($yaRegistrada) {
            return;
        }

        EntradaBitacora::registrar(
            accion: AccionOperativa::ConsultaDatosFiscales,
            descripcion: 'El miembro consultó sus propios datos fiscales.',
            actor: $usuario,
            sujeto: $usuario,
            contexto: ['rfc_parcial' => mb_substr((string) $datos->rfc, 0, 4) . '…'],
        );
    }
}
