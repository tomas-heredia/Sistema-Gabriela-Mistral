<?php

namespace App\Cobranzas\Jobs;

use App\Cobranzas\Mail\ComprobantePagoEnviado;
use App\Cobranzas\Models\PagoCuota;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Solo envía el mail -- el PDF ya se generó y se guardó en disco de forma
 * síncrona en `Registrar::guardar()` (el cobrador lo necesita para
 * descargarlo al toque, no puede esperar a que se procese la cola). Se
 * manda solo al tutor responsable de pago, a diferencia de las libretas
 * (información académica, va a todos los tutores) -- esto es un
 * comprobante de algo que pagó una persona puntual.
 */
class EnviarComprobantePago implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public PagoCuota $pagoCuota,
    ) {}

    public function handle(): void
    {
        $tutor = $this->pagoCuota->cuota->alumno->tutores()->wherePivot('responsable_pago', true)->first()
            ?? $this->pagoCuota->cuota->alumno->tutores()->first();

        if (! $tutor) {
            return;
        }

        Mail::to($tutor->correo)->send(
            new ComprobantePagoEnviado($this->pagoCuota, Storage::disk('local')->path($this->pagoCuota->pdf_path))
        );
    }
}
