<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El cliente de un miembro en una pasarela (hoy, Openpay) y la tarjeta que
 * dejó guardada. La tarjeta vive en Openpay; aquí solo su id y lo que se le
 * muestra a la persona.
 */
class ClientePasarela extends Model
{
    protected $table = 'clientes_pasarela';

    protected $fillable = [
        'user_id', 'pasarela', 'cliente_id',
        'tarjeta_id', 'tarjeta_marca', 'tarjeta_ultimos4', 'tarjeta_vence',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tieneTarjeta(): bool
    {
        return filled($this->tarjeta_id);
    }
}
