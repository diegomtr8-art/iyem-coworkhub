<?php

namespace App\Http\Controllers\Api\Movil;

use App\Enums\EventoAuth;
use App\Exceptions\ErrorDeApi;
use App\Http\Requests\Movil\AccesoConContrasena;
use App\Models\EnlaceMagico;
use App\Models\EventoAutenticacion;
use App\Models\User;
use App\Notifications\EnlaceDeAcceso;
use App\Servicios\Acceso\EmisorDeTokens;
use App\Servicios\Acceso\IdentidadDeProveedor;
use App\Servicios\Acceso\VerificadorDeGoogle;
use App\Support\DosFactores;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Acceso de la app (docs/API-MOVIL.md §5).
 *
 * Tres caminos —contraseña, Google, enlace mágico— y los tres terminan en
 * `EmisorDeTokens`, que decide la cara, exige el segundo factor y emite el
 * token. Ningún camino tiene reglas propias que los otros no tengan.
 */
class AccesoController extends ControladorMovil
{
    public function __construct(private readonly EmisorDeTokens $emisor)
    {
    }

    /** Correo y contraseña. Mismas defensas que el login web. */
    public function token(AccesoConContrasena $request): JsonResponse
    {
        $usuario = $request->usuarioSinSesion();

        return $this->datos($this->emisor->entrar($usuario, $this->dispositivo($request), 'contrasena'));
    }

    /** Segundo paso: el desafío más el código de la app autenticadora o uno de recuperación. */
    public function dosFactores(Request $request, DosFactores $dosFactores): JsonResponse
    {
        $datos = $request->validate([
            'desafio'             => ['required', 'string', 'max:80'],
            'codigo'              => ['nullable', 'string', 'max:20'],
            'codigo_recuperacion' => ['nullable', 'string', 'max:40'],
        ]);

        $recuperacion = (string) ($datos['codigo_recuperacion'] ?? '');

        $resultado = $this->emisor->canjearDesafio(
            $datos['desafio'],
            fn (User $usuario) => $recuperacion !== ''
                ? $dosFactores->consumirCodigoDeRecuperacion($usuario, $recuperacion)
                : $dosFactores->verificarCodigo($usuario->dos_factores_secreto, (string) ($datos['codigo'] ?? '')),
        );

        if ($resultado['fallo']) {
            EventoAutenticacion::registrar(
                EventoAuth::IngresoFallido,
                $resultado['usuario'],
                exito: false,
                contexto: ['motivo' => 'segundo_factor_incorrecto', 'app' => true],
            );

            throw ValidationException::withMessages([
                $recuperacion !== '' ? 'codigo_recuperacion' : 'codigo' => $recuperacion !== ''
                    ? 'Ese código de recuperación no es válido o ya se usó.'
                    : 'Ese código no es válido. Prueba con el siguiente que muestre tu app.',
            ]);
        }

        return $this->datos($this->emisor->emitir(
            $resultado['usuario'],
            $this->emisor->caraDe($resultado['usuario']),
            $resultado['dispositivo'],
            $resultado['via'],
        ));
    }

    /** Google: la app trae un `id_token` y aquí se verifica que es para nosotros. */
    public function google(Request $request, VerificadorDeGoogle $verificador, IdentidadDeProveedor $identidades): JsonResponse
    {
        abort_unless((bool) config('nodico.acceso.google'), 404);

        $request->validate([
            'id_token' => ['required', 'string', 'max:4096'],
            ...AccesoConContrasena::reglasDelDispositivo(),
        ]);

        $perfil = $verificador->verificar($request->string('id_token')->toString());

        if (! $perfil) {
            EventoAutenticacion::registrar(
                EventoAuth::IngresoFallido,
                exito: false,
                contexto: ['proveedor' => 'google', 'motivo' => 'id_token_invalido', 'app' => true],
            );

            throw ValidationException::withMessages([
                'id_token' => 'No pudimos confirmar tu cuenta de Google. Inténtalo de nuevo o entra con tu correo.',
            ]);
        }

        if (! $perfil->correoVerificado) {
            throw ValidationException::withMessages([
                'id_token' => 'Esa cuenta de Google no tiene el correo verificado. Entra con tu correo y contraseña.',
            ]);
        }

        $usuario = $identidades->resolver('google', $perfil);

        if (! $usuario) {
            throw ValidationException::withMessages([
                'id_token' => 'No pudimos vincular esa cuenta de Google con seguridad. Entra con tu contraseña.',
            ]);
        }

        return $this->datos($this->emisor->entrar($usuario, $this->dispositivo($request), 'google'));
    }

    /**
     * Pedir el enlace mágico. Respuesta idéntica exista o no la cuenta: esta
     * ruta no puede servir para averiguar quién está registrado.
     */
    public function pedirEnlace(Request $request): JsonResponse
    {
        abort_unless((bool) config('nodico.acceso.enlace_magico'), 404);

        $datos = $request->validate([
            'email'       => ['required', 'string', 'email', 'max:255'],
            'verificador' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
        ]);

        $correo  = Str::lower(trim($datos['email']));
        $usuario = User::where('email', $correo)->first();

        if ($usuario) {
            $token = EnlaceMagico::emitirParaApp($usuario, $request->ip(), $datos['verificador']);

            try {
                $usuario->notify(new EnlaceDeAcceso($token, paraLaApp: true));
            } catch (Throwable $e) {
                Log::error('No se pudo enviar el enlace mágico de la app.', [
                    'usuario' => $usuario->id,
                    'motivo'  => $e->getMessage(),
                ]);
            }
        }

        EnlaceMagico::limpiarCaducados();

        return $this->datos([
            'enviado' => true,
            'minutos' => EnlaceMagico::MINUTOS_DE_VIDA,
            'message' => 'Si hay una cuenta con ese correo, te llegará un enlace para entrar. Ábrelo en este teléfono.',
        ]);
    }

    /** Canjear el enlace: el token del correo más el secreto que solo este teléfono guarda. */
    public function canjearEnlace(Request $request): JsonResponse
    {
        abort_unless((bool) config('nodico.acceso.enlace_magico'), 404);

        $request->validate([
            'token'   => ['required', 'string', 'regex:/^[A-Za-z0-9]{48}$/'],
            'secreto' => ['required', 'string', 'min:32', 'max:128'],
            ...AccesoConContrasena::reglasDelDispositivo(),
        ]);

        $enlace = EnlaceMagico::with('usuario')->conToken($request->string('token')->toString())->first();

        if (! $enlace || ! $enlace->vigente()) {
            throw new ErrorDeApi(422, 'enlace_invalido', 'Ese enlace ya no sirve: caducó o ya se usó. Pide uno nuevo.');
        }

        if (! $enlace->coincideLaHuella($request->string('secreto')->toString())) {
            EventoAutenticacion::registrar(
                EventoAuth::IngresoFallido,
                $enlace->usuario,
                exito: false,
                contexto: ['motivo' => 'enlace_magico_otro_dispositivo', 'app' => true],
            );

            throw new ErrorDeApi(422, 'enlace_otro_dispositivo', 'Ese enlace se pidió desde otro teléfono, así que aquí no funciona. Pide uno nuevo desde este.');
        }

        // Se marca gastado **antes** de emitir nada, y con una actualización
        // condicional: si dos peticiones llegan a la vez con el mismo enlace,
        // solo una cambia la fila; la otra se queda fuera.
        $gastado = EnlaceMagico::whereKey($enlace->getKey())->whereNull('usado_en')->update(['usado_en' => now()]);

        if ($gastado !== 1) {
            throw new ErrorDeApi(422, 'enlace_invalido', 'Ese enlace ya no sirve: caducó o ya se usó. Pide uno nuevo.');
        }

        return $this->datos($this->emisor->entrar($enlace->usuario, $this->dispositivo($request), 'enlace_magico'));
    }

    /** Cerrar sesión: revoca **este** token (y su registro de avisos, en cascada). */
    public function salir(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $request->user()->currentAccessToken()->delete();

        EventoAutenticacion::registrar(EventoAuth::CierreSesion, $usuario, contexto: ['app' => true]);

        return $this->datos(['salio' => true]);
    }

    public function reenviarVerificacion(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if (! $usuario->hasVerifiedEmail()) {
            $usuario->sendEmailVerificationNotification();
        }

        return $this->datos([
            'message' => 'Te mandamos otro correo de verificación a ' . $usuario->email . '.',
        ]);
    }

    /** @return array{dispositivo: string, dispositivo_id: string, plataforma: ?string} */
    private function dispositivo(Request $request): array
    {
        return [
            'dispositivo'    => $request->string('dispositivo')->toString(),
            'dispositivo_id' => $request->string('dispositivo_id')->toString(),
            'plataforma'     => in_array($p = $request->input('plataforma') ?: $request->header('X-App-Plataforma'), ['ios', 'android'], true)
                ? $p
                : null,
        ];
    }
}
