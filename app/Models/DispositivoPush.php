<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * App móvil — el token de notificaciones de Expo de un teléfono.
 *
 * Va atado al token de Sanctum con el que se registró: al cerrar sesión o al
 * revocar ese teléfono desde «Mi seguridad», la fila cae en cascada y ese
 * teléfono deja de recibir avisos de una cuenta en la que ya no está.
 */
class DispositivoPush extends Model
{
    protected $table = 'dispositivos_push';

    protected $fillable = ['user_id', 'personal_access_token_id', 'expo_push_token', 'plataforma'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }
}
