<?php

namespace App\Http\Controllers;

use App\Enums\AccionOperativa;
use App\Models\DatosFiscales;
use App\Models\EntradaBitacora;
use App\Models\Factura;
use App\Support\CeldaCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Facturación (Fase 3.9).
 *
 * Nódico **no timbra**: esta pantalla prepara lo que hay que pasarle a
 * contabilidad del IYEM. Por eso lo que importa no es el PDF, sino que los
 * datos fiscales estén **completos y validados** antes de mandarlos: una
 * exportación con un RFC mal escrito vuelve días después y hay que rehacerla.
 *
 * Cada pago pendiente sale marcado según si su miembro tiene los datos listos,
 * que es lo que decide si se puede facturar o hay que pedírselos antes.
 */
class FacturasController extends Controller
{
    public function index(Request $request)
    {
        $facturas = Factura::with(['user:id,name,email', 'user.datosFiscales'])
            ->when($request->string('estatus')->toString(), fn ($q, $e) => $q->where('estatus', $e))
            ->when($request->string('buscar')->toString(), function ($q, $texto) {
                $like = '%' . $texto . '%';
                $q->where(fn ($s) => $s->where('folio', 'like', $like)
                    ->orWhere('concepto', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)));
            })
            ->when($request->boolean('solo_facturables'), fn ($q) => $q->whereHas('user.datosFiscales'))
            ->orderByDesc('fecha')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Factura $f) => [
                'id'       => $f->id,
                'folio'    => $f->folio,
                'concepto' => $f->concepto,
                'fecha'    => $f->fecha?->toDateString(),
                'total'    => (float) $f->total,
                'estatus'  => $f->estatus,
                'metodo_pago' => $f->metodo_pago,
                'fecha_pago'  => $f->fecha_pago?->toDateString(),
                'miembro'  => [
                    'id'     => $f->user_id,
                    'nombre' => $f->user?->name,
                    'email'  => $f->user?->email,
                    'url'    => $f->user_id ? route('miembros.show', $f->user_id) : null,
                ],
                // Lo que decide si se puede pasar a contabilidad.
                'fiscales_completos' => (bool) $f->user?->datosFiscales?->estanCompletos(),
                'rfc' => $f->user?->datosFiscales?->rfc,
            ]);

        return Inertia::render('Facturas/Index', [
            'facturas' => $facturas,
            'filtros'  => $request->only(['estatus', 'buscar', 'solo_facturables']),
            'resumen'  => [
                'pendientes' => Factura::where('estatus', 'Pendiente')->count(),
                'pagadas'    => Factura::where('estatus', 'Pagada')->count(),
                'sin_datos_fiscales' => Factura::whereDoesntHave('user.datosFiscales')->count(),
            ],
            'puedeExportar' => $request->user()->can('exportar', DatosFiscales::class),
        ]);
    }

    public function pagar(Request $request, Factura $factura)
    {
        $datos = $request->validate([
            'metodo_pago' => ['required', Rule::in(['Efectivo', 'Transferencia', 'Tarjeta'])],
        ]);

        $factura->update([
            'estatus'     => 'Pagada',
            'metodo_pago' => $datos['metodo_pago'],
            'fecha_pago'  => now()->toDateString(),
        ]);

        return back()->with('success', 'Pago registrado.');
    }

    /**
     * Exporta lo que contabilidad necesita para timbrar.
     *
     * Va detrás de la policy de datos fiscales y **queda en la bitácora**: saca
     * el RFC de mucha gente de golpe, que es exactamente la clase de operación
     * que hay que poder rastrear.
     */
    public function exportar(Request $request): StreamedResponse
    {
        // `Gate::authorize` y no `$this->authorize()`: el `Controller` base de
        // Laravel 12 ya no incluye `AuthorizesRequests`, y llamarlo daba un 500
        // justo en la ruta que saca datos personales.
        Gate::authorize('exportar', DatosFiscales::class);

        $facturas = Factura::with(['user.datosFiscales'])
            ->when($request->string('estatus')->toString(), fn ($q, $e) => $q->where('estatus', $e))
            ->whereHas('user.datosFiscales')
            ->orderByDesc('fecha')
            ->get();

        EntradaBitacora::registrar(
            accion: AccionOperativa::ExportacionFiscal,
            descripcion: 'Exportó ' . $facturas->count() . ' registro(s) con datos fiscales para contabilidad.',
            actor: $request->user(),
            contexto: ['filas' => $facturas->count(), 'estatus' => $request->string('estatus')->toString()],
        );

        $nombre = 'nodico-facturacion-' . now()->toDateString() . '.csv';

        return response()->streamDownload(function () use ($facturas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");

            fputcsv($salida, [
                'Folio', 'Fecha', 'Concepto', 'Total', 'Estatus', 'Metodo de pago',
                'Miembro', 'RFC', 'Razon social', 'Regimen fiscal', 'Uso CFDI',
                'Codigo postal', 'Correo para factura',
            ]);

            // Cada celda pasa por `CeldaCsv`: el nombre del miembro y la razon
            // social los escribe el propio miembro, y este archivo lo abre
            // administracion en Excel con el RFC de todos los demas en las
            // celdas de al lado. Ver `App\Support\CeldaCsv`.
            foreach ($facturas as $f) {
                $d = $f->user?->datosFiscales;

                fputcsv($salida, CeldaCsv::fila([
                    $f->folio,
                    $f->fecha?->toDateString(),
                    $f->concepto,
                    number_format((float) $f->total, 2, '.', ''),
                    $f->estatus,
                    $f->metodo_pago,
                    $f->user?->name,
                    $d?->rfc,
                    $d?->razon_social,
                    $d?->regimen_fiscal,
                    $d?->uso_cfdi,
                    $d?->codigo_postal,
                    $d?->email_facturacion,
                ]));
            }

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
