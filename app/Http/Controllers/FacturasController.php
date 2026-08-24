<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FacturasController extends Controller
{
    public function index(Request $request)
    {
        $facturas = Factura::with('user')
            ->when($request->estatus, fn($q, $s) => $q->where('estatus', $s))
            ->when($request->search, fn($q, $s) => $q->whereHas('user', fn($q) =>
                $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")
            ))
            ->orderByDesc('fecha')
            ->paginate(15)->withQueryString();

        return Inertia::render('Facturas/Index', [
            'facturas' => $facturas,
            'filters'  => $request->only(['estatus', 'search']),
        ]);
    }

    public function pagar(Request $request, Factura $factura)
    {
        $data = $request->validate([
            'metodo_pago' => 'required|in:Efectivo,Transferencia,Tarjeta',
        ]);

        $factura->update([
            'estatus'     => 'Pagada',
            'metodo_pago' => $data['metodo_pago'],
            'fecha_pago'  => now()->toDateString(),
        ]);

        return back()->with('success', 'Factura marcada como pagada.');
    }
}
