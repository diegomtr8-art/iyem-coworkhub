<?php

namespace App\Notifications;

use App\Notifications\Canales\ExpoPush;
use Illuminate\Notifications\Notification;

/**
 * Un aviso push de la app: recordatorio de reserva, membresía por vencer,
 * asesoría confirmada, factura lista (docs/API-MOVIL.md §6.10).
 *
 * Respeta las preferencias que la persona ya tiene en su perfil
 * (`notif_reservas`, `notif_membresia`, `notif_comunidad`): si apagó ese tipo
 * de aviso, no sale.
 */
class AvisoDeLaApp extends Notification
{
    /**
     * @param  string  $preferencia  reservas | membresia | comunidad
     * @param  array<string, mixed>  $datos  lo que la app usa para abrir la pantalla correcta
     */
    public function __construct(
        private readonly string $preferencia,
        private readonly string $titulo,
        private readonly string $cuerpo,
        private readonly array $datos = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        $campo = 'notif_' . $this->preferencia;

        return ($notifiable->{$campo} ?? true) === false ? [] : [ExpoPush::class];
    }

    /** @return array{titulo: string, cuerpo: string, datos: array<string, mixed>} */
    public function toExpoPush(object $notifiable): array
    {
        return ['titulo' => $this->titulo, 'cuerpo' => $this->cuerpo, 'datos' => $this->datos];
    }
}
