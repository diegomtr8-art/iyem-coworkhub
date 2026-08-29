<?php

namespace App\Models;

use App\Enums\BolsaDeHoras;
use App\Enums\TipoEspacio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Espacio extends Model
{
    use HasFactory;

    /**
     * `tipo` se sigue guardando como cadena y **no** se castea al enum a
     * propósito. Un cast usa `from()` y lanza `ValueError` —o sea, un 500— ante
     * un valor que no reconozca, y esta columna lleva años acumulando tipos que
     * ya no existen. Se resuelve siempre con `tipoEnum()`, que devuelve `null`
     * si el valor no es de los vivos. Mismo criterio que `User::getRolAttribute()`.
     */
    protected $appends = ['tipo_label'];

    protected $fillable = [
        'nombre', 'tipo', 'capacidad', 'precio_hora', 'amenidades', 'disponible', 'piso',
        'descripcion', 'medidas', 'incluye', 'cap_herradura', 'cap_mesas',
        'cap_escuela', 'cap_auditorio', 'imagen', 'publicado', 'orden',
        'hora_apertura', 'hora_cierre', 'dias_operacion',
    ];

    protected $casts = [
        'amenidades'     => 'array',
        'incluye'        => 'array',
        'dias_operacion' => 'array',
        'disponible' => 'boolean',
        'publicado'  => 'boolean',
        'precio_hora' => 'decimal:2',
    ];

    public function scopeSalonesPublicados($query)
    {
        return $query->where('tipo', TipoEspacio::SalonEventos->value)
                     ->where('publicado', true)
                     ->orderBy('orden')
                     ->orderBy('nombre');
    }

    /** Espacios que un miembro puede reservar desde el portal. */
    public function scopeReservables($query)
    {
        return $query->where('disponible', true)
                     ->whereIn('tipo', array_column(TipoEspacio::reservables(), 'value'));
    }

    /** Espacios que consumen una bolsa concreta. */
    public function scopeDeBolsa($query, BolsaDeHoras $bolsa)
    {
        $tipos = array_column(
            array_filter(TipoEspacio::cases(), fn (TipoEspacio $t) => $t->bolsa() === $bolsa),
            'value'
        );

        return $query->whereIn('tipo', $tipos);
    }

    public function reservas() { return $this->hasMany(Reserva::class); }
    public function bloqueos() { return $this->hasMany(BloqueoEspacio::class); }
    public function checkins() { return $this->hasMany(Checkin::class); }

    /** Tipo resuelto, o `null` si la fila guarda un valor que ya no existe. */
    public function tipoEnum(): ?TipoEspacio
    {
        return TipoEspacio::tryFrom((string) $this->tipo);
    }

    /** Bolsa que consume reservar este espacio, o `null` si no consume ninguna. */
    public function bolsa(): ?BolsaDeHoras
    {
        return $this->tipoEnum()?->bolsa();
    }

    public function esReservablePorMiembro(): bool
    {
        return (bool) $this->disponible && ($this->tipoEnum()?->reservablePorMiembro() ?? false);
    }

    /**
     * Etiqueta legible del tipo (BUG-06).
     *
     * El `default` devolvía `$this->tipo` en crudo, así que los cuatro tipos
     * con los que opera el negocio —`privado`, `contenido`, `fotografia`,
     * `coworking`— se le enseñaban al usuario en minúsculas y con guion bajo.
     * Ahora el catálogo es exhaustivo y el único hueco posible es una fila con
     * un tipo muerto, que se avisa como tal en vez de disfrazarse.
     */
    public function getTipoLabelAttribute(): string
    {
        return $this->tipoEnum()?->etiqueta() ?? 'Tipo sin catalogar';
    }
}
