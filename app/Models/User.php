<?php

namespace App\Models;

use App\Enums\EstadoCuenta;
use App\Enums\RolUsuario;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    /**
     * A.4 — `tipo` y `estado_cuenta` **no** van aqui.
     *
     * Hoy no era explotable porque `RegisteredUserController` fijaba el valor a
     * mano, pero bastaba un `User::create($request->all())` o un `update()`
     * masivo para que el rol pasara a ser asignable desde el formulario de
     * registro. Se cambian solo por `asignarRol()` y `cambiarEstado()`.
     */
    protected $fillable = [
        'name', 'email', 'password', 'telefono', 'empresa',
        'avatar', 'face_id_ok', 'ocupacion', 'notas_admin',
        'contacto_emergencia_nombre', 'contacto_emergencia_telefono', 'contacto_emergencia_parentesco',
        'notif_reservas', 'notif_membresia', 'notif_comunidad',
    ];

    protected $hidden = [
        'password', 'remember_token', 'verificacion_nonce', 'dos_factores_secreto',
        'email_nuevo_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'face_id_ok'        => 'boolean',
            // Cifrado, no hasheado: hay que poder leerlo para calcular el
            // codigo de cada minuto. Depende de APP_KEY.
            'dos_factores_secreto'       => 'encrypted',
            'dos_factores_confirmado_en' => 'datetime',
            'email_nuevo_expira_en'      => 'datetime',
            'notif_reservas'             => 'boolean',
            'notif_membresia'            => 'boolean',
            'notif_comunidad'            => 'boolean',
        ];
    }

    /** Minutos que vive un enlace de cambio de correo. */
    public const CAMBIO_CORREO_MINUTOS = 60;

    // ── Rol y estado ────────────────────────────────────────────────────────

    /**
     * Rol del usuario, o `null` si el valor guardado no es ninguno de los
     * conocidos.
     *
     * A proposito **no** es un cast de Eloquent: un cast usa `from()` y lanza
     * `ValueError` ante un valor desconocido, o sea un 500. Aqui un rol que no
     * reconocemos es `null`, y `PerteneceAlPortal` lo convierte en un 403 con
     * explicacion. Ese es el fondo del bug A.1.
     */
    public function getRolAttribute(): ?RolUsuario
    {
        return RolUsuario::tryFrom((string) ($this->attributes['tipo'] ?? ''));
    }

    /**
     * Estado de la cuenta. Aqui si hay valor por defecto: un estado ilegible se
     * trata como `pendiente`, que es el mas restrictivo, nunca como activo.
     */
    public function getEstadoAttribute(): EstadoCuenta
    {
        return EstadoCuenta::tryFrom((string) ($this->attributes['estado_cuenta'] ?? ''))
            ?? EstadoCuenta::Pendiente;
    }

    public function esAdmin(): bool     { return $this->rol === RolUsuario::Admin; }
    public function esStaff(): bool     { return $this->rol === RolUsuario::Staff; }
    public function esMiembro(): bool   { return $this->rol === RolUsuario::Miembro; }
    public function esOperativo(): bool { return $this->rol?->esOperativo() ?? false; }

    public function cuentaActiva(): bool { return $this->estado->puedeOperar(); }

    // ── Metodos de acceso ───────────────────────────────────────────────────

    /**
     * Quien entra con Google no tiene contrasena, y eso es legitimo: su metodo
     * de acceso es la identidad externa.
     */
    public function tieneContrasena(): bool
    {
        return filled($this->attributes['password'] ?? null);
    }

    /**
     * E — Documentos legales cuya version vigente esta sin aceptar.
     *
     * Se compara **version por version**, no un si/no: cuando el IYEM publique
     * una version nueva, quien tenga aceptada la anterior vuelve a aparecer
     * aqui y se le pide de nuevo.
     *
     * @return array<int, string>
     */
    public function consentimientosPendientes(): array
    {
        $vigentes = app(\App\Support\DocumentosLegales::class)->versiones();

        $aceptados = $this->consentimientos()
            ->get(['documento', 'version'])
            ->map(fn ($c) => $c->documento . '@' . $c->version)
            ->all();

        $pendientes = [];

        foreach ($vigentes as $documento => $version) {
            if (! in_array($documento . '@' . $version, $aceptados, true)) {
                $pendientes[] = $documento;
            }
        }

        return $pendientes;
    }

    /**
     * D — El segundo factor solo cuenta como activo si se **confirmo** con un
     * codigo real.
     *
     * Un secreto guardado sin confirmar significa que la app de codigos quiza
     * nunca lo escaneo bien; darlo por activo dejaria a la persona fuera de su
     * propia cuenta sin remedio.
     */
    public function tieneDosFactores(): bool
    {
        return $this->dos_factores_confirmado_en !== null
            && filled($this->dos_factores_secreto);
    }

    /**
     * Cuantas formas distintas tiene esta persona de entrar a su cuenta.
     *
     * Es lo que impide desvincular la ultima: quedarse en cero significa
     * perder la cuenta, y desde dentro del propio panel de seguridad.
     */
    public function metodosDeAcceso(): int
    {
        return ($this->tieneContrasena() ? 1 : 0) + $this->identidades()->count();
    }

    /** Unica via para cambiar el rol. `forceFill` porque `tipo` esta fuera de `$fillable`. */
    public function asignarRol(RolUsuario $rol): static
    {
        $this->forceFill(['tipo' => $rol->value])->save();

        return $this;
    }

    public function cambiarEstado(EstadoCuenta $estado): static
    {
        $this->forceFill(['estado_cuenta' => $estado->value])->save();

        return $this;
    }

    /** Ruta de inicio segun el rol. `null` cuando el rol no se reconoce. */
    public function rutaInicio(): ?string
    {
        return $this->rol?->rutaInicio();
    }

    // ── Verificacion de correo de un solo uso (A.2) ──────────────────────────

    /**
     * El enlace de verificacion firma `sha1(getEmailForVerification())`, tanto
     * al generarlo como al comprobarlo. Metiendo un nonce dentro, el enlace
     * muere en cuanto se usa: al verificar se borra el nonce y el hash del
     * correo enviado ya no coincide con nada.
     *
     * Es el punto de extension previsto por Laravel para esto; asi no hay que
     * duplicar la notificacion ni la ruta.
     */
    public function getEmailForVerification(): string
    {
        return $this->email . '|' . (string) ($this->attributes['verificacion_nonce'] ?? '');
    }

    /**
      * Queda en `false` cuando el correo de verificación no llegó a salir.
      *
      * No se persiste: solo vive durante la petición que intentó enviarlo, que
      * es quien tiene que decidir qué contarle a la persona.
      */
    public bool $correoDeVerificacionEnviado = true;

    /**
     * Envía el correo de verificación **sin dejar que su fallo tumbe la
     * petición**.
     *
     * Comprobado en prueba.nodico.com.mx el 29/08/2026: con el SMTP mal
     * configurado, la `TransportException` subía hasta el controlador y el
     * registro devolvía un 500 **después** de haber creado la cuenta. La
     * persona veía una pantalla de error, la cuenta quedaba huérfana y sin
     * verificar, y al reintentar se topaba con que su correo «ya existía».
     *
     * Que el correo no salga es un problema de operación; que se lleve por
     * delante el registro es un defecto. Se registra en el log y quien llama
     * decide el mensaje.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->forceFill(['verificacion_nonce' => Str::random(40)])->save();

        try {
            parent::sendEmailVerificationNotification();
            $this->correoDeVerificacionEnviado = true;
        } catch (Throwable $e) {
            $this->correoDeVerificacionEnviado = false;

            Log::error('No se pudo enviar el correo de verificacion.', [
                'usuario' => $this->id,
                'motivo'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Marca el correo como verificado y quema el nonce en la misma escritura:
     * cualquier enlace de verificacion anterior deja de valer al instante.
     *
     * Sustituye a `markEmailAsVerified()`, que no sabe nada del nonce.
     */
    public function marcarCorreoVerificado(): bool
    {
        return $this->forceFill([
            'email_verified_at'  => $this->freshTimestamp(),
            'verificacion_nonce' => null,
        ])->save();
    }

    // ── Cambio de correo (Fase 4.C) ──────────────────────────────────────────

    /**
     * Registra una solicitud de cambio de correo y devuelve el token en claro
     * para armar el enlace. En la base solo queda el **hash** del token: quien
     * lea la fila no puede reconstruir el enlace.
     */
    public function solicitarCambioDeCorreo(string $correoNuevo): string
    {
        $token = Str::random(48);

        $this->forceFill([
            'email_nuevo'           => Str::lower(trim($correoNuevo)),
            'email_nuevo_token'     => hash('sha256', $token),
            'email_nuevo_expira_en' => now()->addMinutes(self::CAMBIO_CORREO_MINUTOS),
        ])->save();

        return $token;
    }

    /** La dirección pendiente si hay una solicitud viva; `null` si no. */
    public function correoPendiente(): ?string
    {
        if ($this->email_nuevo === null || $this->email_nuevo_expira_en === null) {
            return null;
        }

        return $this->email_nuevo_expira_en->isFuture() ? $this->email_nuevo : null;
    }

    /**
     * ¿El token corresponde a la solicitud viva? Comparación en tiempo constante
     * para no filtrar cuántos caracteres coinciden.
     */
    public function tokenDeCambioValido(string $token): bool
    {
        if ($this->correoPendiente() === null || $this->email_nuevo_token === null) {
            return false;
        }

        return hash_equals($this->email_nuevo_token, hash('sha256', $token));
    }

    /**
     * Aplica el cambio: el correo pendiente pasa a ser el correo, ya verificado
     * (quien pulsó el enlace demostró tener acceso a esa dirección), y se limpia
     * la solicitud. Devuelve el correo anterior por si quien llama quiere avisar.
     */
    public function aplicarCambioDeCorreo(): string
    {
        $anterior = $this->email;

        $this->forceFill([
            'email'                 => $this->email_nuevo,
            'email_verified_at'     => $this->freshTimestamp(),
            'verificacion_nonce'    => null,
            'email_nuevo'           => null,
            'email_nuevo_token'     => null,
            'email_nuevo_expira_en' => null,
        ])->save();

        return $anterior;
    }

    public function cancelarCambioDeCorreo(): void
    {
        $this->forceFill([
            'email_nuevo'           => null,
            'email_nuevo_token'     => null,
            'email_nuevo_expira_en' => null,
        ])->save();
    }

    // ── Consultas ───────────────────────────────────────────────────────────

    public function scopeConRol(Builder $query, RolUsuario ...$roles): Builder
    {
        return $query->whereIn('tipo', array_column($roles, 'value'));
    }

    public function scopeMiembros(Builder $query): Builder
    {
        return $query->where('tipo', RolUsuario::Miembro->value);
    }

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function identidades()    { return $this->hasMany(IdentidadSocial::class); }
    public function consentimientos()       { return $this->hasMany(Consentimiento::class); }
    public function codigosRecuperacion()   { return $this->hasMany(CodigoRecuperacion::class); }
    public function dispositivosConfiables(){ return $this->hasMany(DispositivoConfiable::class); }
    public function enlacesMagicos() { return $this->hasMany(EnlaceMagico::class); }

    public function suscripciones()  { return $this->hasMany(Suscripcion::class); }
    public function suscripcionActiva() { return $this->hasOne(Suscripcion::class)->where('estatus', 'Activa')->latest(); }
    public function reservas()        { return $this->hasMany(Reserva::class); }
    public function checkins()        { return $this->hasMany(Checkin::class); }
    public function facturas()        { return $this->hasMany(Factura::class); }
    public function comunicados()     { return $this->hasMany(Comunicado::class); }
    public function asesorias()       { return $this->hasMany(SolicitudAsesoria::class); }

    /**
     * Datos fiscales. Es `hasOne` y no un puñado de columnas en esta tabla a
     * propósito: son datos personales sensibles, con su propia policy, y aquí
     * viajarían en cada `$request->user()` de cada petición del sitio.
     */
    public function datosFiscales()   { return $this->hasOne(DatosFiscales::class); }
}
