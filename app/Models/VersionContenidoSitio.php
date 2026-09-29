<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cómo estaba una sección del sitio antes de un cambio. La escribe y la lee
 * `ContenidoDelSitio`; nadie más.
 */
class VersionContenidoSitio extends Model
{
    protected $table = 'versiones_contenido_sitio';

    protected $fillable = ['clave', 'valor', 'guardada_por'];

    protected $casts = ['valor' => 'array'];

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardada_por');
    }
}
