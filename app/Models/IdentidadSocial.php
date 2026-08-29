<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * C — Una cuenta externa vinculada a una cuenta de Nódico.
 */
class IdentidadSocial extends Model
{
    protected $table = 'identidades_sociales';

    protected $fillable = [
        'user_id', 'proveedor', 'proveedor_id', 'correo', 'avatar', 'ultimo_acceso_en',
    ];

    protected function casts(): array
    {
        return ['ultimo_acceso_en' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getEtiquetaAttribute(): string
    {
        return match ($this->proveedor) {
            'google' => 'Google',
            'apple'  => 'Apple',
            default  => ucfirst($this->proveedor),
        };
    }
}
