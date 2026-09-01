<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un evento del terminal facial de Smart Pass, ya en Nódico.
 *
 * La ingesta (`IngestaDeEventos`) es quien crea estas filas; nadie más las
 * escribe. `origen_id` es único, así que reinsertar el mismo evento —un reintento
 * del agente— no duplica nada.
 */
class EventoAcceso extends Model
{
    use HasFactory;

    protected $table = 'eventos_acceso';

    protected $fillable = [
        'origen_id', 'ocurrido_en', 'person_id', 'person_type', 'reconocido',
        'pass_type', 'sub_pass_type', 'direction', 'device_id', 'device_key',
        'user_id', 'genero_checkin',
    ];

    protected $casts = [
        'ocurrido_en'    => 'datetime',
        'reconocido'     => 'boolean',
        'genero_checkin' => 'boolean',
        'person_id'      => 'integer',
        'person_type'    => 'integer',
        'direction'      => 'integer',
        'device_id'      => 'integer',
        'origen_id'      => 'integer',
    ];

    /** El miembro amarrado a este evento, o `null` (extraño / sin amarre). */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Un evento es de «extraño» si no lo reconoció el terminal. */
    public function esExtrano(): bool
    {
        return ! $this->reconocido || $this->person_type === -1 || $this->person_id === -1;
    }
}
