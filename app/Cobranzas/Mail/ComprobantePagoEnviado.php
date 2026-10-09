<?php

namespace App\Cobranzas\Mail;

use App\Alumnos\Models\Alumno;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ComprobantePagoEnviado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Alumno $alumno,
        public string $numeroRecibo,
        public string $pdfAbsolutePath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Comprobante de pago de {$this->alumno->nombre}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'pagos.mail.enviado',
            with: [
                'alumno' => $this->alumno,
                'numeroRecibo' => $this->numeroRecibo,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfAbsolutePath)
                ->as("recibo-{$this->numeroRecibo}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
