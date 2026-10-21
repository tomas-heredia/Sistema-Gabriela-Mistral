<?php

namespace App\Mora\Jobs;

use App\Alumnos\Models\Tutor;
use App\Mora\Mail\ResumenDeDeudaEnviado;
use App\Mora\Models\ResumenDeuda;
use App\Mora\Services\CalculadorDeDeuda;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Genera el PDF de resumen de deuda de un tutor y se lo manda por mail.
 * Idempotente por (tutor_id, periodo): si ya existe un ResumenDeuda para
 * este mes, no vuelve a mandar nada -- así GenerarResumenesMensualesDeDeuda
 * puede correr de más sin riesgo de duplicar el envío.
 */
class GenerarYEnviarResumenDeDeuda implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Tutor $tutor,
        public string $periodo,
    ) {}

    public function handle(CalculadorDeDeuda $calculador): void
    {
        if (ResumenDeuda::query()->where('tutor_id', $this->tutor->id)->where('periodo', $this->periodo)->exists()) {
            return;
        }

        $detalle = $calculador->detalleParaTutor($this->tutor);

        // La deuda se pudo haber saldado entre que se encoló el job y que
        // corrió -- no manda un resumen vacío.
        if ($detalle->isEmpty()) {
            return;
        }

        $totalAdeudado = (int) $detalle->sum('subtotal');

        $pdfBinario = Pdf::loadView('mora.pdf.resumen-deuda', [
            'tutor' => $this->tutor,
            'detalle' => $detalle,
            'totalAdeudado' => $totalAdeudado,
            'fechaEmision' => now(),
        ])->output();

        $rutaRelativa = "resumenes-deuda/{$this->tutor->id}/{$this->periodo}.pdf";
        Storage::disk('local')->put($rutaRelativa, $pdfBinario);

        Mail::to($this->tutor->correo)->send(
            new ResumenDeDeudaEnviado($this->tutor, $detalle->pluck('alumno'), Storage::disk('local')->path($rutaRelativa))
        );

        ResumenDeuda::create([
            'tutor_id' => $this->tutor->id,
            'periodo' => $this->periodo,
            'monto_adeudado' => $totalAdeudado,
            'pdf_path' => $rutaRelativa,
            'enviado_at' => now(),
        ]);
    }
}
