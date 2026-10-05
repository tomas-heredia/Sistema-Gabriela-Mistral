<?php

namespace App\Cobranzas\Services;

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Enums\Nivel;
use App\Alumnos\Models\Enums\Turno;
use App\Cobranzas\Models\PagoCuota;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Genera el comprobante en PDF de una cuota pagada y lo deja guardado en
 * disco -- se llama una vez por cada cuota de la operación (un PDF por
 * mes, nunca uno combinado), igual que se haría con un talonario de recibos
 * de papel.
 */
class GeneradorDeComprobantePago
{
    public function generar(PagoCuota $pagoCuota): PagoCuota
    {
        $pagoCuota->loadMissing(['cuota.alumno.tutores', 'cuota.periodoLectivo', 'pago']);

        $pdf = Pdf::loadView('pagos.comprobante', $this->datos($pagoCuota));

        $rutaRelativa = "pagos/comprobantes/{$pagoCuota->id}.pdf";
        Storage::disk('local')->put($rutaRelativa, $pdf->output());

        $pagoCuota->forceFill(['pdf_path' => $rutaRelativa])->save();

        return $pagoCuota;
    }

    /**
     * Datos de un recibo en el formato que espera la vista -- se reutiliza
     * tanto para el PDF individual (`generar()`) como para la recopilación
     * de todos los recibos de un período (`PagosPdfController`), así ambos
     * muestran exactamente el mismo contenido por cuota pagada.
     */
    public function datos(PagoCuota $pagoCuota): array
    {
        $cuota = $pagoCuota->cuota;
        $alumno = $cuota->alumno;
        $tutor = $alumno->tutores->firstWhere('pivot.responsable_pago', true) ?? $alumno->tutores->first();
        $pago = $pagoCuota->pago;

        return [
            'numeroRecibo' => $pagoCuota->numero_recibo,
            'fecha' => $pago->fecha,
            'tutorNombre' => $tutor->nombre,
            'tutorDni' => $tutor->dni,
            'alumnoNombre' => $alumno->nombre,
            'alumnoDni' => $alumno->dni,
            'nivel' => ucfirst($alumno->nivel->value),
            'gradoLinea' => $this->gradoLinea($alumno),
            'periodoLinea' => PeriodoDeCuotaEnTexto::calcular($cuota),
            'formaDePago' => $pago->medio_pago->label(),
            'subtotal' => $cuota->monto_base,
            'descuento' => $cuota->descuento_monto,
            'interes' => $pagoCuota->interes_aplicado,
            'total' => $pagoCuota->montoTotal(),
            'totalEnLetras' => NumeroEnLetras::pesos($pagoCuota->montoTotal()),
        ];
    }

    /**
     * Nivel inicial no tiene un campo de "división" propio (ver
     * `Alumnos\Formulario` -- nunca se le pide uno): la sala se separa en A
     * para el turno mañana y B para el turno tarde, así que se infiere acá
     * para el comprobante en vez de agregar un campo nuevo al alumno.
     */
    private function gradoLinea(Alumno $alumno): string
    {
        $division = $alumno->division
            ?? ($alumno->nivel === Nivel::Inicial
                ? ($alumno->turno === Turno::Manana ? 'A' : 'B')
                : null);

        return collect([$alumno->grado, $division, $alumno->turno->value])
            ->filter()
            ->map(fn ($valor) => is_string($valor) ? ucfirst($valor) : $valor)
            ->implode(' / ');
    }
}
