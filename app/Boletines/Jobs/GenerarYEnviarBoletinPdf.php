<?php

namespace App\Boletines\Jobs;

use App\Boletines\Mail\BoletinEnviado;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\Enums\EstadoTrimestre;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Arma un PDF único y acumulativo (este trimestre + todos los `enviado`
 * previos del mismo boletín — nunca reenvía trimestres viejos por
 * separado) y lo manda por mail a todos los tutores del alumno (no solo
 * el `responsable_pago`: es información académica, a diferencia del aviso
 * de mora). El correo es obligatorio en `Tutor`, así que no hace falta
 * filtrar por si está cargado — solo queda vacío si el alumno no tiene
 * ningún tutor vinculado. Si algo falla, el trimestre queda en `cargado`
 * — no se marca `enviado` hasta que todo termine bien.
 */
class GenerarYEnviarBoletinPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public BoletinTrimestre $trimestre,
    ) {}

    public function handle(): void
    {
        $boletin = $this->trimestre->boletin()->with(['alumno.tutores', 'plantilla', 'periodoLectivo'])->first();

        $trimestres = $boletin->trimestres()
            ->where(function ($query) {
                $query->where('estado', EstadoTrimestre::Enviado)
                    ->orWhere('id', $this->trimestre->id);
            })
            ->orderBy('trimestre')
            ->get();

        $pdfBinario = Pdf::loadView('boletines.pdf', [
            'alumno' => $boletin->alumno,
            'plantilla' => $boletin->plantilla,
            'periodo' => $boletin->periodoLectivo,
            'trimestres' => $trimestres,
        ])->output();

        $rutaRelativa = "boletines/generados/{$boletin->id}/trimestre-{$this->trimestre->trimestre}.pdf";
        Storage::disk('local')->put($rutaRelativa, $pdfBinario);

        $destinatarios = $boletin->alumno->tutores->pluck('correo');

        if ($destinatarios->isNotEmpty()) {
            Mail::to($destinatarios->all())->send(
                new BoletinEnviado($this->trimestre, Storage::disk('local')->path($rutaRelativa))
            );
        }

        $this->trimestre->forceFill([
            'pdf_path' => $rutaRelativa,
            'estado' => EstadoTrimestre::Enviado,
            'fecha_enviado' => now(),
        ])->save();

        $boletin->recalcularEstado();
    }
}
