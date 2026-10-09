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
 *
 * Recibe el numero_recibo, no un PagoCuota puntual: ese número puede estar
 * en varias filas (un pago que cubrió varios meses del mismo alumno), todas
 * comparten el mismo PDF -- alcanza con mandarlo una sola vez.
 */
class EnviarComprobantePago implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $numeroRecibo,
    ) {}

    public function handle(): void
    {
        $pagoCuota = PagoCuota::where('numero_recibo', $this->numeroRecibo)->with('cuota.alumno.tutores')->first();

        if (! $pagoCuota) {
            return;
        }

        $alumno = $pagoCuota->cuota->alumno;
        $tutor = $alumno->tutores()->wherePivot('responsable_pago', true)->first()
            ?? $alumno->tutores()->first();

        if (! $tutor) {
            return;
        }

        Mail::to($tutor->correo)->send(
            new ComprobantePagoEnviado($alumno, $this->numeroRecibo, Storage::disk('local')->path($pagoCuota->pdf_path))
        );
    }
}
