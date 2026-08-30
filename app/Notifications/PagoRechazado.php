<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fase 4.A — aviso al miembro de que un cobro de su membresía falló.
 *
 * Va cuando el webhook de Stripe informa de un `invoice.payment_failed`: la
 * cuenta queda suspendida y el miembro se entera con qué hacer, en vez de
 * descubrir de golpe que no puede reservar.
 */
class PagoRechazado extends Notification
{
    use Queueable;

    public function __construct(private readonly string $motivo)
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
            ->subject('No pudimos cobrar tu membresía — Nódico')
            ->view('emails.pago-rechazado', [
                'nombre' => $this->primerNombre($notifiable),
                'motivo' => $this->motivo,
            ]);
    }

    private function primerNombre(object $notifiable): string
    {
        $nombre = trim((string) ($notifiable->name ?? ''));

        return $nombre === '' ? 'Hola' : explode(' ', $nombre)[0];
    }
}
