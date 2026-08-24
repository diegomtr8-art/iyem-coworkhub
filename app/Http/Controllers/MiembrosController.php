<?php

namespace App\Http\Controllers;

use App\Models\Comunicado;
use App\Models\Factura;
use App\Models\Plane;
use App\Models\Suscripcion;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MiembrosController extends Controller
{
    public function index(Request $request)
    {
        $miembros = User::where('tipo', 'miembro')
            ->with(['suscripciones' => fn($q) => $q->where('estatus', 'Activa')->with('plan')])
            ->when($request->search, fn($q, $s) => $q->where(fn($q) =>
                $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")
            ))
            ->paginate(10)->withQueryString();

        return Inertia::render('Miembros/Index', [
            'miembros' => $miembros,
            'filters'  => $request->only(['search']),
        ]);
    }

    public function show(User $miembro)
    {
        $miembro->load([
            'suscripciones.plan',
            'suscripciones.companion',
            'reservas.espacio',
            'checkins.espacio',
            'facturas',
        ]);

        return Inertia::render('Miembros/Show', [
            'miembro'  => $miembro,
            'planes'   => Plane::where('activo', true)->get(),
            'miembros' => User::where('tipo', 'miembro')->where('id', '!=', $miembro->id)->select('id','name','email')->get(),
        ]);
    }

    public function crearSuscripcion(Request $request, User $miembro)
    {
        $data = $request->validate([
            'plan_id'       => 'required|exists:planes,id',
            'fecha_inicio'  => 'required|date',
            'fecha_fin'     => 'required|date|after:fecha_inicio',
            'precio_pagado' => 'required|numeric|min:0',
            'auto_renovar'  => 'boolean',
        ]);

        // Desactivar suscripciones previas
        $miembro->suscripciones()->where('estatus', 'Activa')->update(['estatus' => 'Cancelada']);

        $suscripcion = Suscripcion::create([
            ...$data,
            'user_id' => $miembro->id,
            'estatus' => 'Activa',
        ]);

        $plan  = Plane::find($data['plan_id']);
        $folio = 'NOD-' . str_pad(Factura::count() + 1, 6, '0', STR_PAD_LEFT);
        Factura::create([
            'user_id'        => $miembro->id,
            'suscripcion_id' => $suscripcion->id,
            'folio'          => $folio,
            'concepto'       => "Membresía {$plan->nombre}",
            'fecha'          => now()->toDateString(),
            'subtotal'       => round($data['precio_pagado'] / 1.16, 2),
            'iva'            => round($data['precio_pagado'] - ($data['precio_pagado'] / 1.16), 2),
            'total'          => $data['precio_pagado'],
            'estatus'        => 'Pendiente',
        ]);

        Comunicado::create([
            'user_id' => $miembro->id,
            'titulo'  => "Tu membresía {$plan->nombre} está activa",
            'mensaje' => "Tu membresía ha sido activada. Vigencia hasta " . date('d/m/Y', strtotime($data['fecha_fin'])) . ". ¡Bienvenido a Nodico!",
            'tipo'    => 'info',
            'leido'   => false,
        ]);

        return back()->with('success', 'Membresía activada exitosamente.');
    }

    public function toggleFaceId(Request $request, User $miembro)
    {
        $miembro->update(['face_id_ok' => !$miembro->face_id_ok]);
        $estado = $miembro->fresh()->face_id_ok ? 'registrado' : 'removido';
        return back()->with('success', "Face ID {$estado} correctamente.");
    }

    public function setCompanion(Request $request, User $miembro)
    {
        $data = $request->validate([
            'suscripcion_id'       => 'required|exists:suscripciones,id',
            'companion_user_id'    => 'nullable|exists:users,id',
            'companion_face_id_ok' => 'boolean',
        ]);

        $suscripcion = Suscripcion::findOrFail($data['suscripcion_id']);
        $suscripcion->update([
            'companion_user_id'    => $data['companion_user_id'],
            'companion_face_id_ok' => $data['companion_face_id_ok'] ?? false,
        ]);

        return back()->with('success', 'Acompañante actualizado correctamente.');
    }
}
