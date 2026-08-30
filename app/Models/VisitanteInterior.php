<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fase 4.E — un emprendedor o artesano del interior que vino con day-pass
 * gratuito. Ficha ligera, sin cuenta: recepción la crea en el mostrador y la
 * reencuentra por teléfono o nombre si la persona vuelve.
 */
class VisitanteInterior extends Model
{
    protected $table = 'visitantes_interior';

    protected $fillable = ['nombre', 'telefono', 'municipio', 'giro', 'como_se_entero'];

    public function visitas(): HasMany
    {
        return $this->hasMany(VisitaInterior::class, 'visitante_interior_id');
    }
}
