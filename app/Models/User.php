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
    ];

    protected $hidden = ['password', 'remember_token', 'verificacion_nonce'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'face_id_ok'        => 'boolean',
        ];
    }

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

    public function suscripciones()  { return $this->hasMany(Suscripcion::class); }
    public function suscripcionActiva() { return $this->hasOne(Suscripcion::class)->where('estatus', 'Activa')->latest(); }
    public function reservas()        { return $this->hasMany(Reserva::class); }
    public function checkins()        { return $this->hasMany(Checkin::class); }
    public function facturas()        { return $this->hasMany(Factura::class); }
    public function comunicados()     { return $this->hasMany(Comunicado::class); }
}
