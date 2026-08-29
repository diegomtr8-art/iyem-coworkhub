<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * C — Enlace de acceso de un solo uso.
 *
 * Todo lo delicado se guarda hasheado: ni el token ni la huella del navegador
 * existen en claro en la base. Quien lea esta tabla no puede entrar a ninguna
 * cuenta con lo que hay dentro.
 */
class EnlaceMagico extends Model
{
    protected $table = 'enlaces_magicos';

    protected $fillable = ['user_id', 'token', 'huella', 'ip', 'expira_en', 'usado_en'];

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'usado_en'  => 'datetime',
        ];
    }

    /** Minutos que vive un enlace. Corto a propósito: es una credencial completa. */
    public const MINUTOS_DE_VIDA = 15;

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function hashear(string $valor): string
    {
        return hash('sha256', $valor);
    }

    /**
     * Emite un enlace para esta persona e invalida los anteriores.
     *
     * @return array{token: string, secreto: string}  lo que viaja en la URL y
     *         lo que queda en la sesion de quien lo pidio
     */
    public static function emitir(User $usuario, ?string $ip): array
    {
        // Pedir uno nuevo mata los que hubiera vivos: en cualquier momento hay
        // como mucho un enlace utilizable por cuenta.
        static::where('user_id', $usuario->id)->whereNull('usado_en')->delete();

        $token   = Str::random(48);
        $secreto = Str::random(48);

        static::create([
            'user_id'   => $usuario->id,
            'token'     => static::hashear($token),
            'huella'    => static::hashear($secreto),
            'ip'        => $ip,
            'expira_en' => now()->addMinutes(self::MINUTOS_DE_VIDA),
        ]);

        return ['token' => $token, 'secreto' => $secreto];
    }

    public function vigente(): bool
    {
        return $this->usado_en === null && $this->expira_en->isFuture();
    }

    /** Comparacion en tiempo constante: el token es una credencial. */
    public function coincideLaHuella(?string $secreto): bool
    {
        if (! is_string($secreto) || $secreto === '') {
            return false;
        }

        return hash_equals($this->huella, static::hashear($secreto));
    }

    public function scopeConToken(Builder $query, string $token): Builder
    {
        return $query->where('token', static::hashear($token));
    }

    /** Limpia los caducados; lo llama el propio flujo, sin depender de un cron. */
    public static function limpiarCaducados(): void
    {
        static::where('expira_en', '<', now()->subDay())->delete();
    }
}
