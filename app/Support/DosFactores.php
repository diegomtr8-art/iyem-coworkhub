<?php

namespace App\Support;

use App\Models\CodigoRecuperacion;
use App\Models\DispositivoConfiable;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * D — Segundo factor basado en TOTP (RFC 6238).
 *
 * Compatible con Google Authenticator, Authy y 1Password, sin ninguna
 * dependencia de pago ni servicio externo: el código lo calculan el teléfono y
 * el servidor por separado a partir del mismo secreto y de la hora.
 *
 * **Nada de SMS.** Es caro por mensaje y vulnerable a suplantación de SIM; el
 * NIST lo desaconseja como segundo factor desde 2016.
 */
class DosFactores
{
    /** Cuántos códigos de recuperación se entregan de una tanda. */
    public const CUANTOS_CODIGOS = 8;

    /** Días que dura un dispositivo marcado como de confianza. */
    public const DIAS_DE_CONFIANZA = 30;

    /** Nombre de la cookie firmada del dispositivo de confianza. */
    public const COOKIE = 'nodico_dispositivo';

    public function __construct(private readonly Google2FA $totp)
    {
        // Una ventana de 1 acepta el código anterior y el siguiente, o sea
        // ±30 s. Sin margen, un reloj de teléfono desfasado unos segundos deja
        // a la persona fuera sin explicación posible.
        $this->totp->setWindow(1);
    }

    public function generarSecreto(): string
    {
        return $this->totp->generateSecretKey(32);
    }

    public function verificarCodigo(string $secreto, string $codigo): bool
    {
        $codigo = preg_replace('/\D/', '', $codigo) ?? '';

        if (strlen($codigo) !== 6) {
            return false;
        }

        return $this->totp->verifyKey($secreto, $codigo);
    }

    /** URI `otpauth://` que codifica el QR. */
    public function uri(User $usuario, string $secreto): string
    {
        return $this->totp->getQRCodeUrl(
            // Lo que se ve en la app: «Nódico (correo)».
            config('app.name', 'Nódico'),
            $usuario->email,
            $secreto,
        );
    }

    /**
     * QR como SVG generado **en el servidor**.
     *
     * Nada de librerías de QR en el cliente ni de servicios externos: mandar el
     * `otpauth://` a una API de terceros para que dibuje la imagen seria
     * entregarle el secreto del segundo factor a un desconocido.
     */
    public function qr(User $usuario, string $secreto): string
    {
        $escritor = new Writer(new ImageRenderer(
            new RendererStyle(220, 1),
            new SvgImageBackEnd(),
        ));

        return $escritor->writeString($this->uri($usuario, $secreto));
    }

    // ── Códigos de recuperación ──────────────────────────────────────────────

    /**
     * Genera una tanda nueva y borra la anterior.
     *
     * Devuelve los códigos **en claro**, que es la única vez que existen así:
     * en la base solo queda su hash.
     *
     * @return array<int, string>
     */
    public function generarCodigosDeRecuperacion(User $usuario): array
    {
        $usuario->codigosRecuperacion()->delete();

        $codigos = [];

        for ($i = 0; $i < self::CUANTOS_CODIGOS; $i++) {
            // 16 caracteres de un alfabeto de 32 ≈ 80 bits de entropía.
            // Agrupados de cuatro en cuatro porque se copian a mano.
            $bruto = Str::upper(Str::random(16));
            $bruto = preg_replace('/[^A-Z0-9]/', 'X', $bruto);
            $codigo = implode('-', str_split($bruto, 4));

            CodigoRecuperacion::create([
                'user_id' => $usuario->id,
                'hash'    => CodigoRecuperacion::hashear($codigo),
            ]);

            $codigos[] = $codigo;
        }

        return $codigos;
    }

    /** Gasta un código de recuperación. Cada uno sirve una sola vez. */
    public function consumirCodigoDeRecuperacion(User $usuario, string $codigo): bool
    {
        $fila = $usuario->codigosRecuperacion()
            ->whereNull('usado_en')
            ->where('hash', CodigoRecuperacion::hashear($codigo))
            ->first();

        if (! $fila) {
            return false;
        }

        $fila->forceFill(['usado_en' => now()])->save();

        return true;
    }

    // ── Dispositivos de confianza ────────────────────────────────────────────

    /**
     * Marca este navegador como de confianza durante 30 días.
     *
     * La cookie va **firmada** (fuera de `EncryptCookies` no habría nada que
     * impidiera inventarse una) y lo que guarda es un token aleatorio; el hash
     * queda en la base para poder revocarlo desde «Mi seguridad».
     */
    public function confiarEnEsteDispositivo(User $usuario, Request $peticion): void
    {
        $token = Str::random(48);

        DispositivoConfiable::create([
            'user_id'   => $usuario->id,
            'token'     => DispositivoConfiable::hashear($token),
            'ip'        => $peticion->ip(),
            'agente'    => Str::limit((string) $peticion->userAgent(), 500),
            'expira_en' => now()->addDays(self::DIAS_DE_CONFIANZA),
        ]);

        Cookie::queue(Cookie::make(
            self::COOKIE,
            $token,
            self::DIAS_DE_CONFIANZA * 24 * 60,
            secure: (bool) config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }

    public function esDispositivoDeConfianza(User $usuario, Request $peticion): bool
    {
        $token = $peticion->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return false;
        }

        return DispositivoConfiable::where('user_id', $usuario->id)
            ->where('token', DispositivoConfiable::hashear($token))
            ->where('expira_en', '>', now())
            ->exists();
    }

    // ── Interruptor por rol ──────────────────────────────────────────────────

    /**
     * ¿Esta persona **debe** tener segundo factor?
     *
     * Hoy la lista está vacía: es opcional para todos, como decidió Nódico. El
     * interruptor queda listo porque es muy probable que el IYEM lo exija para
     * el portal operativo cuando el sistema maneje cobros, y entonces basta con
     * añadir el rol en `config/nodico.php`.
     */
    public function esObligatorioPara(User $usuario): bool
    {
        $roles = (array) config('nodico.dos_factores.obligatorio_para', []);

        return $usuario->rol !== null && in_array($usuario->rol->value, $roles, true);
    }
}
