<?php

namespace App\Http\Controllers\Api\Movil;

use App\Enums\AccionOperativa;
use App\Enums\EventoAuth;
use App\Exceptions\ErrorDeApi;
use App\Http\Requests\GuardarDatosFiscalesRequest;
use App\Http\Resources\Movil\UsuarioMovil;
use App\Models\Consentimiento;
use App\Models\DatosFiscales;
use App\Models\DispositivoPush;
use App\Models\EntradaBitacora;
use App\Models\EventoAutenticacion;
use App\Support\DocumentosLegales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * «Yo»: perfil, foto, datos fiscales, teléfonos con sesión, consentimiento,
 * avisos push y borrar la cuenta (docs/API-MOVIL.md §6.1 y §6.10).
 *
 * Contraseña, segundo factor, cambio de correo y cuentas vinculadas **no** están
 * aquí: la app abre `/seguridad` en el navegador. Son flujos con confirmación
 * de contraseña y correos firmados que ya funcionan, y una segunda copia sería
 * la que se queda sin actualizar.
 */
class YoController extends ControladorMovil
{
    public function mostrar(Request $request): JsonResponse
    {
        return $this->datos(new UsuarioMovil($request->user()));
    }

    /** Mismos campos y validaciones que «Mi perfil» en la web. */
    public function actualizar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre'    => ['sometimes', 'required', 'string', 'max:120'],
            'telefono'  => ['sometimes', 'nullable', 'string', 'max:30'],
            'empresa'   => ['sometimes', 'nullable', 'string', 'max:120'],
            'ocupacion' => ['sometimes', 'nullable', 'string', 'max:120'],

            'contacto_emergencia'            => ['sometimes', 'array'],
            'contacto_emergencia.nombre'     => ['nullable', 'string', 'max:120'],
            'contacto_emergencia.telefono'   => ['nullable', 'string', 'max:30'],
            'contacto_emergencia.parentesco' => ['nullable', 'string', 'max:60'],

            'preferencias'           => ['sometimes', 'array'],
            'preferencias.reservas'  => ['boolean'],
            'preferencias.membresia' => ['boolean'],
            'preferencias.comunidad' => ['boolean'],
        ], [
            'nombre.required' => 'Escribe tu nombre.',
        ]);

        $usuario  = $request->user();
        $cambios  = [];
        $mapa     = ['nombre' => 'name', 'telefono' => 'telefono', 'empresa' => 'empresa', 'ocupacion' => 'ocupacion'];

        foreach ($mapa as $entrada => $columna) {
            if (array_key_exists($entrada, $datos)) {
                $cambios[$columna] = $datos[$entrada];
            }
        }

        if (isset($datos['contacto_emergencia'])) {
            $contacto = $datos['contacto_emergencia'];

            // El contacto de emergencia solo sirve si se puede llamar.
            if (filled($contacto['nombre'] ?? null) && blank($contacto['telefono'] ?? null)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'contacto_emergencia.telefono' => 'Hace falta un teléfono al que llamar. '
                        . 'Un contacto de emergencia sin número no sirve de nada.',
                ]);
            }

            $cambios['contacto_emergencia_nombre']     = $contacto['nombre'] ?? null;
            $cambios['contacto_emergencia_telefono']   = $contacto['telefono'] ?? null;
            $cambios['contacto_emergencia_parentesco'] = $contacto['parentesco'] ?? null;
        }

        foreach (['reservas', 'membresia', 'comunidad'] as $pref) {
            if (isset($datos['preferencias'][$pref])) {
                $cambios['notif_' . $pref] = (bool) $datos['preferencias'][$pref];
            }
        }

        $usuario->update($cambios);

        return $this->datos(new UsuarioMovil($usuario->refresh()));
    }

    public function foto(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'foto.max'   => 'La imagen no puede pasar de 4 MB.',
            'foto.image' => 'Sube una imagen (JPG, PNG o WebP).',
        ]);

        $usuario = $request->user();

        // La anterior se borra: si no, cada cambio deja un archivo huérfano.
        if ($usuario->avatar) {
            Storage::disk('public')->delete($usuario->avatar);
        }

        $usuario->update(['avatar' => $request->file('foto')->store('avatares', 'public')]);

        return $this->datos(['avatar_url' => UsuarioMovil::urlPublica($usuario->avatar)]);
    }

    public function borrarFoto(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario->avatar) {
            Storage::disk('public')->delete($usuario->avatar);
            $usuario->update(['avatar' => null]);
        }

        return $this->datos(['avatar_url' => null]);
    }

    public function datosFiscales(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $datos   = $usuario->datosFiscales;

        // Dato personal sensible: la lectura también queda en la bitácora,
        // agrupada por hora como en la web.
        if ($datos) {
            $this->registrarConsultaFiscal($usuario, $datos);
        }

        return $this->datos([
            'datos' => $datos?->only(['rfc', 'razon_social', 'regimen_fiscal', 'uso_cfdi', 'codigo_postal', 'email_facturacion']),
            'completos' => (bool) $datos?->estanCompletos(),
            'actualizados_el' => $datos?->updated_at?->toIso8601String(),
            'regimenes' => collect(config('sat.regimenes'))
                ->map(fn (array $r, string $clave) => ['clave' => $clave, 'nombre' => $r['nombre'], 'personas' => $r['personas']])
                ->values(),
            'usos_cfdi' => collect(config('sat.usos_cfdi'))
                ->map(fn (string $nombre, string $clave) => ['clave' => $clave, 'nombre' => $nombre])
                ->values(),
            'uso_predeterminado' => config('sat.uso_cfdi_predeterminado'),
            'proceso' => [
                'emisor'       => 'Contabilidad del Instituto Yucateco de Emprendedores',
                'dias_habiles' => (int) config('sat.dias_habiles_emision'),
                'contacto'     => config('sat.contacto_facturacion'),
            ],
            'correo_cuenta' => $usuario->email,
        ]);
    }

    public function guardarDatosFiscales(GuardarDatosFiscalesRequest $request): JsonResponse
    {
        $usuario = $request->user();
        $previos = $usuario->datosFiscales;
        $campos  = ['rfc', 'razon_social', 'regimen_fiscal', 'uso_cfdi', 'codigo_postal', 'email_facturacion'];

        $datos = DatosFiscales::updateOrCreate(
            ['user_id' => $usuario->id],
            [...$request->validated(), 'actualizado_por_user_id' => $usuario->id],
        );

        EntradaBitacora::registrar(
            accion: AccionOperativa::CambioDatosFiscales,
            descripcion: $previos
                ? 'Cambio de datos fiscales por el propio miembro (app).'
                : 'Alta de datos fiscales por el propio miembro (app).',
            actor: $usuario,
            sujeto: $usuario,
            contexto: ['antes' => $previos?->only($campos), 'despues' => $datos->only(array_keys($request->validated()))],
        );

        return $this->datos([
            'datos'     => $datos->only($campos),
            'completos' => $datos->estanCompletos(),
            'message'   => $previos ? 'Datos fiscales actualizados.' : 'Datos fiscales guardados. Ya puedes pedir factura.',
        ]);
    }

    /** Los teléfonos con sesión abierta en la app. */
    public function dispositivos(Request $request): JsonResponse
    {
        $actual = $request->user()->currentAccessToken();

        return $this->datos($request->user()->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PersonalAccessToken $t) => [
                'id'         => $t->id,
                'nombre'     => $t->name,
                'plataforma' => $t->plataforma,
                'ultimo_uso' => $t->last_used_at?->toIso8601String(),
                'creado'     => $t->created_at?->toIso8601String(),
                'es_este'    => $actual && $t->id === $actual->id,
            ])
            ->values());
    }

    /** Cerrar sesión de otro teléfono (uno perdido, por ejemplo). */
    public function revocarDispositivo(Request $request, int $id): JsonResponse
    {
        $usuario = $request->user();
        $token   = $usuario->tokens()->whereKey($id)->first();

        abort_unless($token, 404);

        $token->delete();

        EventoAutenticacion::registrar(EventoAuth::CierreRemoto, $usuario, contexto: ['app' => true, 'dispositivo' => $token->name]);

        return $this->datos(['revocado' => true]);
    }

    public function consentimiento(Request $request, DocumentosLegales $documentos): JsonResponse
    {
        return $this->datos([
            'pendientes' => collect($request->user()->consentimientosPendientes())
                ->map(fn (string $clave) => [
                    'documento' => $clave,
                    'version'   => $documentos->version($clave),
                    'titulo'    => $documentos->leer($clave)['titulo'],
                    'url'       => route(DocumentosLegales::DOCUMENTOS[$clave]['ruta']),
                ])
                ->values(),
        ]);
    }

    public function aceptarConsentimiento(Request $request): JsonResponse
    {
        $request->validate(
            ['acepta' => 'accepted'],
            ['acepta.accepted' => 'Necesitamos que aceptes para poder continuar.'],
        );

        Consentimiento::registrar($request->user(), $request);

        return $this->datos(['aceptado' => true, 'message' => 'Gracias. Queda registrado.']);
    }

    /** Registrar el token de avisos de Expo de este teléfono. */
    public function registrarPush(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'expo_push_token' => ['required', 'string', 'max:255', 'regex:/^(ExponentPushToken|ExpoPushToken)\[[A-Za-z0-9_-]+\]$/'],
            'plataforma'      => ['nullable', 'in:ios,android'],
        ]);

        $token = $request->user()->currentAccessToken();

        // Un token de avisos pertenece a un teléfono: si antes lo tenía otra
        // cuenta (el teléfono cambió de dueño), pasa a esta.
        DispositivoPush::updateOrCreate(
            ['expo_push_token' => $datos['expo_push_token']],
            [
                'user_id'                  => $request->user()->id,
                'personal_access_token_id' => $token->id,
                'plataforma'               => $datos['plataforma'] ?? null,
            ],
        );

        return $this->datos(['registrado' => true]);
    }

    public function quitarPush(Request $request): JsonResponse
    {
        DispositivoPush::where('personal_access_token_id', $request->user()->currentAccessToken()->id)->delete();

        return $this->datos(['registrado' => false]);
    }

    /**
     * Borrar la cuenta. Apple lo exige a toda app que permita crear cuentas, y
     * esta las crea al entrar con Google. Exige la contraseña; una cuenta sin
     * contraseña (solo Google) tiene que ponerse una en la web primero.
     */
    public function borrarCuenta(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if (! $usuario->tieneContrasena()) {
            throw new ErrorDeApi(422, 'requiere_contrasena', 'Para borrar tu cuenta, primero ponle una contraseña desde Seguridad.');
        }

        $request->validate([
            'password' => ['required', 'current_password:sanctum'],
        ], [
            'password.current_password' => 'La contraseña no es correcta.',
        ]);

        // Cancela la renovación en Stripe, las referencias sin pagar y todas
        // las sesiones antes de borrar. Ver BorradorDeCuenta.
        app(\App\Servicios\Acceso\BorradorDeCuenta::class)->borrar($usuario);

        return $this->datos(['borrada' => true]);
    }

    private function registrarConsultaFiscal($usuario, DatosFiscales $datos): void
    {
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
            descripcion: 'El miembro consultó sus propios datos fiscales (app).',
            actor: $usuario,
            sujeto: $usuario,
            contexto: ['rfc_parcial' => mb_substr((string) $datos->rfc, 0, 4) . '…'],
        );
    }
}
