<?php

namespace App\Notifications;

use App\Models\OrdenPago;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fase 6 — aviso al miembro cuando se genera su referencia de pago.
 *
 * Lo esencial (monto y referencia) va en el cuerpo del correo: no obliga a entrar
 * al portal para saber cuánto y con qué concepto pagar.
 */
class ReferenciaGenerada extends Notification
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
            ->subject('Tu referencia de pago — Nódico')
            ->view('emails.pago-referencia-generada', [
                'nombre'       => $this->primerNombre($notifiable),
                'referencia'   => $o->referencia,
                'monto'        => (float) $o->monto,
                'vence'        => $o->vence_el,
                'metodo'       => $o->metodo->etiqueta(),
                'pideFactura'  => (bool) $o->pide_factura,
            ]);
    }

    private function primerNombre(object $notifiable): string
    {
        $nombre = trim((string) ($notifiable->name ?? ''));

        return $nombre === '' ? 'Hola' : explode(' ', $nombre)[0];
    }
}
