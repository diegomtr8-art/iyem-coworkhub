<?php

namespace App\Models;

use App\Enums\CategoriaPersonaAcceso;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Empleado del IYEM o prestador de servicio social con acceso por rostro.
 *
 * `smartpass_person_id` (el FaceID) NO es asignable en masa a propósito: se
 * amarra por una acción explícita del panel (vincular), no por un formulario.
 */
class PersonaAcceso extends Model
{
    use HasFactory;

    protected $table = 'personas_acceso';

    protected $fillable = [
        'categoria', 'nombre', 'correo', 'telefono', 'identificador',
        'puesto', 'inicio', 'fin', 'activo', 'notas',
    ];

    protected $casts = [
        'categoria' => CategoriaPersonaAcceso::class,
        'inicio'    => 'date',
        'fin'       => 'date',
        'activo'    => 'boolean',
    ];

    public function scopeDeCategoria(Builder $q, CategoriaPersonaAcceso $categoria): Builder
    {
        return $q->where('categoria', $categoria->value);
    }

    /** ¿Tiene su rostro amarrado a Smart Pass? */
    public function tieneRostro(): bool
    {
        return $this->smartpass_person_id !== null;
    }
}
