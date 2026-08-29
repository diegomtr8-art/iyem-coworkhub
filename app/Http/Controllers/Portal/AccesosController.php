<?php

namespace App\Http\Controllers\Portal;

use App\Enums\BolsaDeHoras;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResuelveLaMembresia;
use App\Models\Checkin;
use App\Models\Factura;
use App\Models\MovimientoHoras;
use App\Servicios\Horas\LibroDeHoras;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Mis accesos y mis pagos (Fase 2.8).
 *
 * Historial de entradas y salidas, días consumidos si el plan va por días, y
 * pagos con su estado. Se juntan en una pantalla porque responden a la misma
 * pregunta de fondo —«¿qué he usado y qué he pagado?»— y separarlas obligaría a
 * ir y volver para cuadrar una cosa con la otra.
 */
class AccesosController extends Controller
{
    use ResuelveLaMembresia;

    public function __construct(private readonly LibroDeHoras $libro)
    {
    }

    public function index(Request $request)
    {
        $usuario     = $request->user();
        $suscripcion = $this->membresiaVigente($usuario);
        $porDias     = $suscripcion && ! ($suscripcion->plan?->esIlimitado() ?? true);

        return Inertia::render('Portal/Accesos', [
            'accesos' => $usuario->checkins()
                ->with('espacio:id,nombre,tipo')
                ->latest('hora_entrada')
                ->limit(60)
                ->get()
                ->map(fn (Checkin $acceso) => [
                    'id'          => $acceso->id,
                    'espacio'     => $acceso->espacio?->nombre,
                    'entrada'     => $acceso->hora_entrada->toIso8601String(),
                    'salida'      => $acceso->hora_salida?->toIso8601String(),
                    'minutos'     => $acceso->duracion_minutos,
                    'abierto'     => $acceso->estaActivo(),
                    'con_reserva' => ! is_null($acceso->reserva_id),
                ]),

            // Solo tiene sentido en Day-Pass y Flex: en un plan ilimitado, un
            // medidor de días es ruido.
            'dias' => $porDias ? [
                'cupo'      => BolsaDeHoras::Dias->cupo($suscripcion->plan),
                'usado'     => $this->libro->consumoDelCiclo($suscripcion, BolsaDeHoras::Dias),
                'restante'  => $this->libro->saldoDelCiclo($suscripcion, BolsaDeHoras::Dias),
                'vence_el'  => $suscripcion->fecha_fin->toDateString(),
            ] : null,

            'ilimitado' => $suscripcion ? ($suscripcion->plan?->esIlimitado() ?? false) : false,

            'pagos' => $usuario->facturas()
                ->orderByDesc('fecha')
                ->limit(40)
                ->get()
                ->map(fn (Factura $factura) => [
                    'id'          => $factura->id,
                    'folio'       => $factura->folio,
                    'concepto'    => $factura->concepto,
                    'fecha'       => $factura->fecha?->toDateString(),
                    'total'       => (float) $factura->total,
                    'estatus'     => $factura->estatus,
                    'metodo_pago' => $factura->metodo_pago,
                    'fecha_pago'  => $factura->fecha_pago?->toDateString(),
                ]),

            // El estado de los datos fiscales decide si se puede pedir factura,
            // y decirlo aquí evita el viaje a la otra pantalla para descubrirlo.
            'datosFiscales' => [
                'completos' => (bool) $usuario->datosFiscales?->estanCompletos(),
                'url'       => route('portal.datos-fiscales'),
            ],

            'facturacion' => [
                'emisor'       => 'Contabilidad del Instituto Yucateco de Emprendedores',
                'dias_habiles' => (int) config('sat.dias_habiles_emision'),
                'contacto'     => config('sat.contacto_facturacion'),
            ],

            // El libro de horas, en cristiano. Es lo que permite al miembro
            // entender su saldo sin preguntar en recepción.
            'movimientos' => $suscripcion
                ? MovimientoHoras::where('suscripcion_id', $suscripcion->id)
                    ->with('reserva.espacio:id,nombre')
                    ->latest('id')
                    ->limit(40)
                    ->get()
                    ->map(fn (MovimientoHoras $m) => [
                        'id'          => $m->id,
                        'descripcion' => $m->descripcion(),
                        'bolsa'       => $m->bolsa->etiqueta(),
                        'cantidad'    => $m->cantidad,
                        'consume'     => $m->consume(),
                        'motivo'      => $m->motivo->value,
                        'nota'        => $m->nota,
                        'espacio'     => $m->reserva?->espacio?->nombre,
                        'fecha'       => $m->created_at->toIso8601String(),
                    ])
                : [],
        ]);
    }
}
