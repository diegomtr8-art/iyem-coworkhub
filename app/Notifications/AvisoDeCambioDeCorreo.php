<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fase 4.C — aviso a la dirección **anterior** de que alguien pidió cambiar el
 * correo de la cuenta.
 *
 * Es la red de seguridad del secuestro de sesión: si el cambio no lo pidió el
 * dueño, este se entera en la dirección que todavía controla y llega a tiempo
 * de cambiar la contraseña. Por eso este correo **no** lleva enlace de acción
 * —no queremos que un clic apresurado confirme nada—: solo informa y dice qué
 * hacer si no fue él.
 */
class AvisoDeCambioDeCorreo extends Notification
{
    use Queueable;

    public function __construct(private readonly string $correoNuevo)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Se solicitó cambiar el correo de tu cuenta — Nódico')
            ->view('emails.aviso-cambio-correo', [
                'nombre'      => $this->primerNombre($notifiable),
                'correoNuevo' => $this->correoNuevo,
            ]);
    }

    private function primerNombre(object $notifiable): string
    {
        $nombre = trim((string) ($notifiable->name ?? ''));

        return $nombre === '' ? 'Hola' : explode(' ', $nombre)[0];
    }
}
