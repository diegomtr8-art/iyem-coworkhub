<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fase 4.C — verificación de la dirección **nueva** en un cambio de correo.
 *
 * Va a la dirección nueva, no a la de la cuenta. Confirma dos cosas a la vez:
 * que la dirección existe y que quien la pidió tiene acceso a ella. Hasta que
 * se pulsa, el correo de la cuenta no se toca.
 */
class VerificarCorreoNuevo extends Notification
{
    use Queueable;

    public function __construct(private readonly string $url)
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
            ->subject('Confirma tu nuevo correo — Nódico')
            ->view('emails.verificar-correo-nuevo', [
                'nombre'  => $this->primerNombre($notifiable),
                'url'     => $this->url,
                'minutos' => User::CAMBIO_CORREO_MINUTOS,
            ]);
    }

    private function primerNombre(object $notifiable): string
    {
        $nombre = trim((string) ($notifiable->name ?? ''));

        return $nombre === '' ? 'Hola' : explode(' ', $nombre)[0];
    }
}
