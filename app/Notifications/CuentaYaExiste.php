<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * B — Se envía cuando alguien intenta registrarse con un correo que ya tiene
 * cuenta.
 *
 * Es la pieza que hace posible ocultar la existencia de la cuenta sin dejar a
 * nadie perdido: el formulario responde exactamente igual que ante un alta
 * nueva, y la aclaración viaja por el único canal al que solo puede llegar el
 * dueño de la dirección.
 *
 * No lleva enlace que inicie sesión: manda al acceso normal y a recuperar la
 * contraseña, que ya tienen sus propias defensas.
 */
class CuentaYaExiste extends Notification
{
    use Queueable;

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
            ->subject('Ya tienes cuenta en Nódico')
            ->view('emails.cuenta-ya-existe', [
                'nombre'      => $this->primerNombre($notifiable),
                'correo'      => $notifiable->email,
                'urlAcceso'   => route('login'),
                'urlOlvide'   => route('password.request'),
            ]);
    }

    private function primerNombre(object $notifiable): string
    {
        $nombre = trim((string) ($notifiable->name ?? ''));

        return $nombre === '' ? 'Hola' : explode(' ', $nombre)[0];
    }
}
