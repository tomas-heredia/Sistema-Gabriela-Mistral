<?php

namespace App\Boletines\Mail;

use App\Boletines\Models\BoletinTrimestre;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BoletinEnviado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BoletinTrimestre $trimestre,
        public string $pdfAbsolutePath,
        public bool $esCorreccion = false,
    ) {}

    public function envelope(): Envelope
    {
        $alumno = $this->trimestre->boletin->alumno;
        $asunto = $this->esCorreccion ? "Corrección de la libreta de {$alumno->nombre}" : "Libreta de {$alumno->nombre}";

        return new Envelope(
            subject: $asunto,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'boletines.mail.enviado',
            with: [
                'alumno' => $this->trimestre->boletin->alumno,
                'esCorreccion' => $this->esCorreccion,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfAbsolutePath)
                ->as('libreta.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
