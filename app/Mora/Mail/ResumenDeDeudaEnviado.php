<?php

namespace App\Mora\Mail;

use App\Alumnos\Models\Tutor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ResumenDeDeudaEnviado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tutor $tutor,
        public Collection $alumnosConDeuda,
        public string $pdfAbsolutePath,
    ) {}

    public function envelope(): Envelope
    {
        $mes = ucfirst(now()->translatedFormat('F'));

        return new Envelope(
            subject: "Resumen de deuda — Colegio Gabriela Mistral — {$mes} ".now()->year,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mora.mail.enviado',
            with: [
                'tutor' => $this->tutor,
                'alumnosConDeuda' => $this->alumnosConDeuda,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfAbsolutePath)
                ->as('resumen-de-deuda.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
