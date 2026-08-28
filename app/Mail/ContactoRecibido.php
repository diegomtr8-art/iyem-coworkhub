<?php

namespace App\Mail;

use App\Models\Contacto;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactoRecibido extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Contacto $contacto) {}

    public function envelope(): Envelope
    {
        $asunto = $this->contacto->asunto ?: 'Nuevo mensaje del sitio';

        return new Envelope(
            subject: "[Nódico web] {$asunto} — {$this->contacto->nombre}",
            replyTo: [$this->contacto->email],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contacto');
    }
}
