<?php

namespace App\Http\Controllers;

use App\Mail\ContactoRecibido;
use App\Models\Comunicado;
use App\Models\Contacto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactoController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:100',
            'telefono'    => 'nullable|string|max:40',
            'email'       => 'required|email|max:150',
            'empresa'     => 'nullable|string|max:150',
            'asunto'      => 'nullable|string|max:200',
            'comentarios' => 'required|string|max:2000',
        ], [], [
            'nombre'      => 'nombre',
            'telefono'    => 'teléfono',
            'email'       => 'e-mail',
            'empresa'     => 'empresa',
            'asunto'      => 'asunto',
            'comentarios' => 'comentarios',
        ]);

        $contacto = Contacto::create($data + ['ip' => $request->ip()]);

        // Copia interna para el panel de administración.
        if ($admin = User::where('tipo', 'admin')->first()) {
            Comunicado::create([
                'user_id' => $admin->id,
                'titulo'  => "Contacto web: {$contacto->nombre} ({$contacto->email})",
                'mensaje' => "Asunto: " . ($contacto->asunto ?: 'No especificado')
                    . "\nEmpresa: " . ($contacto->empresa ?: '—')
                    . "\nTeléfono: " . ($contacto->telefono ?: '—')
                    . "\n\n" . $contacto->comentarios,
                'tipo'    => 'info',
                'leido'   => false,
            ]);
        }

        // El correo no debe tumbar la petición si el SMTP falla.
        try {
            Mail::to(config('nodico.contacto_email'))->send(new ContactoRecibido($contacto));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo de contacto', [
                'contacto_id' => $contacto->id,
                'error'       => $e->getMessage(),
            ]);
        }

        return back()->with('contacto_ok', true);
    }
}
