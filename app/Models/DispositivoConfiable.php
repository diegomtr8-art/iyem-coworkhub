<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D — Navegador en el que no se vuelve a pedir el segundo factor durante 30
 * dias.
 *
 * Existe como fila y no solo como cookie **para poder revocarlo**: si alguien
 * marca «confiar» en un equipo prestado, tiene que poder quitarlo desde «Mi
 * seguridad» sin acceso a ese equipo.
 */
class DispositivoConfiable extends Model
{
    protected $table = 'dispositivos_confiables';

    protected $fillable = ['user_id', 'token', 'ip', 'agente', 'expira_en'];

    protected function casts(): array
    {
        return ['expira_en' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function hashear(string $token): string
    {
        return hash('sha256', $token);
    }
}
