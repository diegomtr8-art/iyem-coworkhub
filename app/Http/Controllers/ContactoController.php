<?php

namespace App\Http\Controllers;

use App\Models\Comunicado;
use App\Models\User;
use Illuminate\Http\Request;

class ContactoController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'  => 'required|string|max:100',
            'email'   => 'required|email|max:150',
            'telefono'=> 'nullable|string|max:20',
            'interes' => 'nullable|string|max:100',
            'mensaje' => 'required|string|max:2000',
        ]);

        // Guardar como comunicado interno para el admin
        $admin = User::where('tipo', 'admin')->first();
        if ($admin) {
            Comunicado::create([
                'user_id' => $admin->id,
                'titulo'  => "Contacto web: {$data['nombre']} ({$data['email']})",
                'mensaje' => "Interés: " . ($data['interes'] ?? 'No especificado') . "\n\n" . $data['mensaje'] . "\n\nTeléfono: " . ($data['telefono'] ?? '—'),
                'tipo'    => 'info',
                'leido'   => false,
            ]);
        }

        return back()->with('contacto_ok', true);
    }
}
