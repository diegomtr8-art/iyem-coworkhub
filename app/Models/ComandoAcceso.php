<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una orden encolada de Nódico hacia el agente (p. ej. «abrir puerta»).
 *
 * El agente la recoge por HTTP saliente, la ejecuta contra Smart Pass y reporta
 * el resultado. Ver la migración para el porqué de la cola.
 */
class ComandoAcceso extends Model
{
    protected $table = 'comandos_acceso';

    public const ABRIR_PUERTA     = 'abrir_puerta';
    public const ENROLAR_ROSTRO   = 'enrolar_rostro';
    public const BORRAR_ROSTRO    = 'borrar_rostro';
    public const RECONECTAR_TORNO = 'reconectar_torno';
    public const CAPTURAR_FR07    = 'capturar_fr07';

    protected $fillable = [
        'tipo', 'device_id', 'payload', 'person_id', 'estado', 'resultado',
        'solicitado_por_user_id', 'enviado_en', 'resuelto_en',
    ];

    protected $casts = [
        'device_id'   => 'integer',
        'payload'     => 'array',
        'person_id'   => 'integer',
        'enviado_en'  => 'datetime',
        'resuelto_en' => 'datetime',
    ];

    public function solicitante()
    {
        return $this->belongsTo(User::class, 'solicitado_por_user_id');
    }
}
