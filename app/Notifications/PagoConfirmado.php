<?php

namespace App\Notifications;

use App\Models\OrdenPago;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fase 6 — aviso al miembro cuando caja confirma su pago y la membresía se activa.
 */
class PagoConfirmado extends Notification
{
    use Queueable;

    public function __construct(private readonly OrdenPago $orden)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $o = $this->orden->loadMissing('suscripcion', 'plan');

        return (new MailMessage)
            ->subject('Tu pago está confirmado — Nódico')
            ->view('emails.pago-confirmado', [
                'nombre'      => $this->primerNombre($notifiable),
                'plan'        => $o->plan?->nombre,
                'vigencia'    => $o->suscripcion?->fecha_fin,
                'pideFactura' => (bool) $o->pide_factura,
            ]);
    }

    private function primerNombre(object $notifiable): string
    {
        $nombre = trim((string) ($notifiable->name ?? ''));

        return $nombre === '' ? 'Hola' : explode(' ', $nombre)[0];
    }
}
