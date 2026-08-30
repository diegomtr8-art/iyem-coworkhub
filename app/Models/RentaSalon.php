<?php

namespace App\Models;

use App\Enums\EstadoRentaSalon;
use App\Enums\TipoMontaje;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Renta o cotización de un salón Yucatán Emprende (Fase 3.5).
 *
 * Los importes viven **congelados** en la fila: precio por hora, precio del
 * coffee break y totales. No se recalculan al leer. Una cotización enviada en
 * marzo tiene que seguir diciendo lo que se cotizó aunque la tarifa haya
 * cambiado en abril; recalcular al vuelo convierte cada consulta del histórico
 * en una cifra distinta a la que firmó el cliente.
 */
class RentaSalon extends Model
{
    use HasFactory;

    protected $table = 'rentas_salon';

    protected $fillable = [
        'espacio_id', 'cliente_nombre', 'cliente_email', 'cliente_telefono', 'cliente_empresa',
        'user_id', 'contacto_id', 'evento_nombre', 'fecha', 'hora_inicio', 'hora_fin',
        'montaje', 'personas', 'precio_hora', 'horas', 'subtotal_salon',
        'con_coffee_break', 'coffee_personas', 'coffee_precio_persona', 'subtotal_coffee',
        'descuento', 'total', 'anticipo', 'anticipo_pagado_el', 'estado', 'notas',
        'creado_por_user_id', 'bloqueo_id',
    ];

    protected $casts = [
        'fecha'                 => 'date',
        'anticipo_pagado_el'    => 'date',
        'con_coffee_break'      => 'boolean',
        'precio_hora'           => 'decimal:2',
        'horas'                 => 'float',
        'subtotal_salon'        => 'decimal:2',
        'coffee_precio_persona' => 'decimal:2',
        'subtotal_coffee'       => 'decimal:2',
        'descuento'             => 'decimal:2',
        'total'                 => 'decimal:2',
        'anticipo'              => 'decimal:2',
        'estado'                => EstadoRentaSalon::class,
        'montaje'               => TipoMontaje::class,
    ];

    public function espacio()  { return $this->belongsTo(Espacio::class); }
    public function cliente()  { return $this->belongsTo(User::class, 'user_id'); }
    public function contacto() { return $this->belongsTo(Contacto::class); }
    public function creadoPor() { return $this->belongsTo(User::class, 'creado_por_user_id'); }
    public function bloqueo()  { return $this->belongsTo(BloqueoEspacio::class, 'bloqueo_id'); }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->whereIn('estado', [
            EstadoRentaSalon::Cotizacion->value,
            EstadoRentaSalon::Confirmada->value,
        ]);
    }

    public function scopeDelMes(Builder $query, string $mes): Builder
    {
        return $query->whereYear('fecha', substr($mes, 0, 4))
                     ->whereMonth('fecha', substr($mes, 5, 2));
    }

    /** Lo que falta por cobrar. */
    public function saldoPendiente(): float
    {
        return max(0, round((float) $this->total - (float) $this->anticipo, 2));
    }

    public function anticipoPagado(): bool
    {
        return ! is_null($this->anticipo_pagado_el) && (float) $this->anticipo > 0;
    }

    /**
     * Si el aforo pedido cabe en el montaje elegido.
     *
     * Devuelve `null` cuando falta el dato para decidirlo —sin montaje, o sin
     * capacidad capturada en el salón—, que no es lo mismo que «no cabe».
     */
    public function aforoAlcanza(): ?bool
    {
        $capacidad = $this->montaje?->capacidadEn($this->espacio);

        if ($capacidad === null || $this->personas <= 0) {
            return null;
        }

        return $this->personas <= $capacidad;
    }
}
