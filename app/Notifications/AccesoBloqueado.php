<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * B — Aviso al titular cuando su cuenta se bloquea por intentos fallidos.
 *
 * Para quien de verdad es dueño de la cuenta, este correo suele ser la única
 * señal de que alguien está probando contraseñas contra ella. No lleva enlace
 * de acceso ni contraseña: solo cuenta lo que pasó y a dónde ir.
 */
class AccesoBloqueado extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $ip,
        private readonly string $agente,
        private readonly int $segundos,
    ) {
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
            ->subject('Intentos de acceso a tu cuenta — Nódico')
            ->view('emails.acceso-bloqueado', [
                'nombre'  => $this->primerNombre($notifiable),
                'ip'      => $this->ip,
                'agente'  => $this->agente,
                'minutos' => max(1, (int) ceil($this->segundos / 60)),
                'cuando'  => now()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            ]);
    }

    private function primerNombre(object $notifiable): string
    {
        $nombre = trim((string) ($notifiable->name ?? ''));

        return $nombre === '' ? 'Hola' : explode(' ', $nombre)[0];
    }
}
