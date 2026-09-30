<?php

namespace App\Http\Controllers;

use App\Enums\RolUsuario;
use App\Mail\ContactoRecibido;
use App\Models\Comunicado;
use App\Models\Contacto;
use App\Models\User;
use App\Servicios\Sitio\ContenidoDelSitio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactoController extends Controller
{
    public function store(Request $request)
    {
        // Trampa antibots: el campo va oculto, si trae contenido lo llenó un robot.
        // Se responde como si todo hubiera salido bien para no darle pistas.
        if (filled($request->input('sitio_web'))) {
            return back()->with('contacto_ok', true);
        }

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

        // BE-03: se guarda el teléfono normalizado a E.164 y también tal cual
        // lo escribió la persona, para no perder su formato.
        $contacto = Contacto::create($data + [
            'ip'            => $request->ip(),
            'telefono_e164' => $this->normalizarTelefono($data['telefono'] ?? null),
        ]);

        // Copia interna para el panel de administración.
        if ($admin = User::conRol(RolUsuario::Admin)->first()) {
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

        // El correo no debe tumbar la petición si el SMTP falla: el mensaje ya
        // está guardado. Pero el fallo se queda en el prospecto, que es lo que
        // el panel enseña; solo en el log no lo veía nadie.
        try {
            Mail::to(app(ContenidoDelSitio::class)->valor('contacto', 'email'))->send(new ContactoRecibido($contacto));
        } catch (\Throwable $e) {
            $contacto->update(['correo_error' => mb_substr($e->getMessage(), 0, 500)]);
            Log::error('No se pudo enviar el correo de contacto', [
                'contacto_id' => $contacto->id,
                'error'       => $e->getMessage(),
            ]);
        }

        return back()->with('contacto_ok', true);
    }

    /**
     * BE-03 — normaliza a E.164 asumiendo México (+52) cuando no viene prefijo.
     * Devuelve null si no hay suficientes dígitos para ser un teléfono.
     */
    private function normalizarTelefono(?string $valor): ?string
    {
        if (! $valor) {
            return null;
        }

        $tieneMas = str_starts_with(trim($valor), '+');
        $digitos = preg_replace('/\D/', '', $valor);

        if (strlen($digitos) < 10) {
            return null;
        }

        if ($tieneMas) {
            return '+' . $digitos;
        }

        // 10 dígitos = número nacional mexicano.
        if (strlen($digitos) === 10) {
            return '+52' . $digitos;
        }

        // Ya trae lada de país.
        return '+' . $digitos;
    }
}
