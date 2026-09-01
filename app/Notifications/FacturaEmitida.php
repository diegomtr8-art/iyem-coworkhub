<?php

namespace App\Notifications;

use App\Models\OrdenPago;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fase 6 — aviso al miembro cuando su factura está emitida, con el enlace de
 * descarga. Al enviarse, la orden pasa a `enviada`.
 */
class FacturaEmitida extends Notification
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
        $o = $this->orden;

        return (new MailMessage)
            ->subject('Tu factura de Nódico')
            ->view('emails.factura-emitida', [
                'nombre'     => $this->primerNombre($notifiable),
                'referencia' => $o->referencia,
                'folio'      => $o->folio_fiscal,
                'urlPagos'   => route('portal.pagos'),
            ]);
    }

    private function primerNombre(object $notifiable): string
    {
        $nombre = trim((string) ($notifiable->name ?? ''));

        return $nombre === '' ? 'Hola' : explode(' ', $nombre)[0];
    }
}
