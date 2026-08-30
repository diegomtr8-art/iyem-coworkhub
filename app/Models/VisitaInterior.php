<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fase 4.E — una visita concreta de un visitante del interior (un day-pass
 * usado un día). Es su «registro de acceso»: aparece en el tablero del día como
 * cualquier otro visitante.
 */
class VisitaInterior extends Model
{
    protected $table = 'visitas_interior';

    protected $fillable = ['visitante_interior_id', 'fecha', 'registrado_por_user_id'];

    protected $casts = ['fecha' => 'date'];

    public function visitante(): BelongsTo
    {
        return $this->belongsTo(VisitanteInterior::class, 'visitante_interior_id');
    }
}
