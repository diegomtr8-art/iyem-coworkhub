<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FacturasController extends Controller
{
    public function index(Request $request)
    {
        $facturas = $request->user()
            ->facturas()
            ->with('suscripcion.plan')
            ->orderByDesc('fecha')
            ->paginate(10)->withQueryString();

        return Inertia::render('Portal/MisFacturas', ['facturas' => $facturas]);
    }
}
