<?php

namespace App\Cobranzas\Mail;

use App\Cobranzas\Models\PagoCuota;
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
        public PagoCuota $pagoCuota,
        public string $pdfAbsolutePath,
    ) {}

    public function envelope(): Envelope
    {
        $alumno = $this->pagoCuota->cuota->alumno;

        return new Envelope(
            subject: "Comprobante de pago de {$alumno->nombre}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'pagos.mail.enviado',
            with: [
                'alumno' => $this->pagoCuota->cuota->alumno,
                'numeroRecibo' => $this->pagoCuota->numero_recibo,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfAbsolutePath)
                ->as("recibo-{$this->pagoCuota->numero_recibo}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
