<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Mi perfil (Fase 2.2).
 *
 * Datos personales, foto, contacto de emergencia y preferencias de aviso. La
 * parte de seguridad —contraseña, segundo factor, sesiones abiertas— **no se
 * duplica aquí**: se enlaza a lo que ya se construyó en el trabajo de
 * autenticación. Dos formularios de contraseña en dos sitios distintos es la
 * receta para que uno de los dos se quede sin la validación del otro.
 */
class PerfilController extends Controller
{
    public function edit(Request $request)
    {
        $usuario = $request->user();

        return Inertia::render('Portal/Perfil', [
            'perfil' => [
                'name'      => $usuario->name,
                'email'     => $usuario->email,
                'telefono'  => $usuario->telefono,
                'empresa'   => $usuario->empresa,
                'ocupacion' => $usuario->ocupacion,
                'avatar'    => $usuario->avatar ? Storage::url($usuario->avatar) : null,

                'contacto_emergencia_nombre'     => $usuario->contacto_emergencia_nombre,
                'contacto_emergencia_telefono'   => $usuario->contacto_emergencia_telefono,
                'contacto_emergencia_parentesco' => $usuario->contacto_emergencia_parentesco,

                'notif_reservas'  => (bool) $usuario->notif_reservas,
                'notif_membresia' => (bool) $usuario->notif_membresia,
                'notif_comunidad' => (bool) $usuario->notif_comunidad,
            ],

            'faceIdOk' => (bool) $usuario->face_id_ok,

            // El bloque de seguridad solo enlaza; la pantalla vive en /seguridad.
            'seguridad' => [
                'url'                => route('seguridad'),
                'dos_factores_activo' => ! is_null($usuario->dos_factores_confirmado_en),
                'correo_verificado'  => ! is_null($usuario->email_verified_at),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'telefono'  => ['nullable', 'string', 'max:30'],
            'empresa'   => ['nullable', 'string', 'max:120'],
            'ocupacion' => ['nullable', 'string', 'max:120'],

            'contacto_emergencia_nombre'     => ['nullable', 'string', 'max:120'],
            'contacto_emergencia_telefono'   => ['nullable', 'string', 'max:30'],
            'contacto_emergencia_parentesco' => ['nullable', 'string', 'max:60'],

            'notif_reservas'  => ['boolean'],
            'notif_membresia' => ['boolean'],
            'notif_comunidad' => ['boolean'],
        ], [
            'name.required' => 'Escribe tu nombre.',
        ]);

        // El contacto de emergencia solo sirve si se puede llamar: un nombre sin
        // teléfono es tan inútil como no tenerlo, y peor, porque aparenta estar.
        if (filled($datos['contacto_emergencia_nombre'] ?? null)
            && blank($datos['contacto_emergencia_telefono'] ?? null)) {
            return back()->withErrors([
                'contacto_emergencia_telefono' => 'Hace falta un teléfono al que llamar. '
                    . 'Un contacto de emergencia sin número no sirve de nada.',
            ]);
        }

        $usuario->update($datos);

        return back()->with('success', 'Perfil actualizado.');
    }

    /**
     * El correo se cambia por `ProfileController`, que ya exige la contraseña
     * actual. Aquí solo se sube la foto.
     */
    public function avatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'avatar.max'   => 'La imagen no puede pasar de 4 MB.',
            'avatar.image' => 'Sube una imagen (JPG, PNG o WebP).',
        ]);

        $usuario = $request->user();

        // La anterior se borra: si no, cada cambio de foto deja un archivo
        // huérfano en disco para siempre.
        if ($usuario->avatar) {
            Storage::disk('public')->delete($usuario->avatar);
        }

        $usuario->update([
            'avatar' => $request->file('avatar')->store('avatares', 'public'),
        ]);

        return back()->with('success', 'Foto actualizada.');
    }

    public function borrarAvatar(Request $request)
    {
        $usuario = $request->user();

        if ($usuario->avatar) {
            Storage::disk('public')->delete($usuario->avatar);
            $usuario->update(['avatar' => null]);
        }

        return back()->with('success', 'Foto eliminada.');
    }
}
