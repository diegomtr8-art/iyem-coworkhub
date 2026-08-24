<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    protected $table = 'facturas';

    protected $fillable = [
        'user_id', 'suscripcion_id', 'folio', 'concepto', 'fecha',
        'subtotal', 'iva', 'total', 'estatus', 'metodo_pago', 'fecha_pago',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_pago' => 'date',
        'subtotal' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function suscripcion() { return $this->belongsTo(Suscripcion::class); }
}
