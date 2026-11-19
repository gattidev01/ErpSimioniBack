<?php

namespace App\Mail;

use App\Models\OficinaConfig;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** E-mail para o cliente (orçamento/OS com PDF anexo, ou lembrete) */
class MensagemCliente extends Mailable
{
    public function __construct(
        public string $assunto,
        public string $texto,
        public ?string $pdf = null,
        public ?string $nomePdf = null,
    ) {}

    public function envelope(): Envelope
    {
        $oficina = OficinaConfig::atual();

        return new Envelope(
            subject: $this->assunto,
            replyTo: $oficina->email ? [$oficina->email] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.mensagem', with: ['oficina' => OficinaConfig::atual()]);
    }

    public function attachments(): array
    {
        return $this->pdf
            ? [Attachment::fromData(fn () => $this->pdf, $this->nomePdf ?? 'documento.pdf')->withMime('application/pdf')]
            : [];
    }
}
