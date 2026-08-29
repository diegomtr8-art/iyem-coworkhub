<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D — Un codigo de recuperacion del segundo factor.
 *
 * Se guarda **hasheado**: solo hay que comprobarlo, nunca volver a mostrarlo.
 * Por eso los codigos se ensenan una unica vez al generarlos y despues solo se
 * pueden regenerar. Si se pudieran volver a ver, no estarian hasheados de
 * verdad.
 */
class CodigoRecuperacion extends Model
{
    protected $table = 'codigos_recuperacion';

    protected $fillable = ['user_id', 'hash', 'usado_en'];

    protected function casts(): array
    {
        return ['usado_en' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Se normaliza antes de hashear: la gente los copia a mano, con espacios de
     * mas, en minusculas o sin los guiones. Sin esto, un codigo correcto
     * tecleado con otro formato se rechazaria.
     */
    public static function hashear(string $codigo): string
    {
        $normalizado = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo) ?? '');

        return hash('sha256', $normalizado);
    }
}
