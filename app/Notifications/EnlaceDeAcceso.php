<?php

namespace App\Notifications;

use App\Models\EnlaceMagico;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * C — Enlace de acceso sin contrasena.
 *
 * Este es el unico correo de Nodico que **si** lleva un enlace que inicia
 * sesion, y por eso es el que mas restricciones tiene: un solo uso, quince
 * minutos y atado al navegador que lo pidio. La regla general de la fase B
 * —«ningun correo debe incluir un enlace que inicie sesion»— se rompe aqui a
 * proposito, porque es el mecanismo mismo, no un atajo de comodidad.
 */
class EnlaceDeAcceso extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token)
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
            ->subject('Tu enlace de acceso — Nódico')
            ->view('emails.enlace-magico', [
                'nombre'  => $this->primerNombre($notifiable),
                'url'     => route('enlace-magico.entrar', ['token' => $this->token]),
                'minutos' => EnlaceMagico::MINUTOS_DE_VIDA,
            ]);
    }

    private function primerNombre(object $notifiable): string
    {
        $nombre = trim((string) ($notifiable->name ?? ''));

        return $nombre === '' ? 'Hola' : explode(' ', $nombre)[0];
    }
}
