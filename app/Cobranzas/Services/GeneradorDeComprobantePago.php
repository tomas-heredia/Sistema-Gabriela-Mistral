<?php

namespace App\Cobranzas\Services;

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Enums\Nivel;
use App\Alumnos\Models\Enums\Turno;
use App\Cobranzas\Models\PagoCuota;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Genera el comprobante en PDF de un alumno dentro de una operación de pago,
 * y lo deja guardado en disco. Si ese pago cubre varios meses del mismo
 * alumno, todas esas cuotas comparten un único comprobante (una sola fila
 * por período dentro del mismo PDF) en vez de uno por mes -- quien paga
 * varios meses juntos se lleva un solo papel, no un talonario.
 */
class GeneradorDeComprobantePago
{
    /**
     * @param  Collection<int, PagoCuota>  $pagoCuotas  Todas las filas que
     *                                                  comparten un mismo numero_recibo (mismo pago, mismo alumno) -- ver
     *                                                  AsignadorDePagos::aplicar().
     */
    public function generar(Collection $pagoCuotas): Collection
    {
        $pagoCuotas->each->loadMissing(['cuota.alumno.tutores', 'cuota.periodoLectivo', 'pago']);

        $pdf = Pdf::loadView('pagos.comprobante', $this->datos($pagoCuotas));

        $numeroRecibo = $pagoCuotas->first()->numero_recibo;
        $rutaRelativa = "pagos/comprobantes/{$numeroRecibo}.pdf";
        Storage::disk('local')->put($rutaRelativa, $pdf->output());

        foreach ($pagoCuotas as $pagoCuota) {
            $pagoCuota->forceFill(['pdf_path' => $rutaRelativa])->save();
        }

        return $pagoCuotas;
    }

    /**
     * Datos de un recibo en el formato que espera la vista -- se reutiliza
     * tanto para el PDF individual (`generar()`) como para la recopilación
     * de todos los recibos de un período (`PagosPdfController`), así ambos
     * muestran exactamente el mismo contenido.
     *
     * @param  Collection<int, PagoCuota>  $pagoCuotas
     */
    public function datos(Collection $pagoCuotas): array
    {
        $primera = $pagoCuotas->first();
        $cuota = $primera->cuota;
        $alumno = $cuota->alumno;
        $tutor = $alumno->tutores->firstWhere('pivot.responsable_pago', true) ?? $alumno->tutores->first();
        $pago = $primera->pago;

        $lineas = $pagoCuotas->map(fn (PagoCuota $pagoCuota) => [
            'periodo' => PeriodoDeCuotaEnTexto::calcular($pagoCuota->cuota),
            'subtotal' => $pagoCuota->cuota->monto_base,
            'descuento' => $pagoCuota->cuota->descuento_monto,
            'interes' => $pagoCuota->interes_aplicado,
            'total' => $pagoCuota->montoTotal(),
        ]);

        return [
            'numeroRecibo' => $primera->numero_recibo,
            'fecha' => $pago->fecha,
            'tutorNombre' => $tutor->nombre,
            'tutorDni' => $tutor->dni,
            'alumnoNombre' => $alumno->nombre,
            'alumnoDni' => $alumno->dni,
            'nivel' => ucfirst($alumno->nivel->value),
            'gradoLinea' => $this->gradoLinea($alumno),
            'formaDePago' => $pago->medio_pago->label(),
            'lineas' => $lineas,
            'subtotal' => $lineas->sum('subtotal'),
            'descuento' => $lineas->sum('descuento'),
            'interes' => $lineas->sum('interes'),
            'total' => $lineas->sum('total'),
            'totalEnLetras' => NumeroEnLetras::pesos($lineas->sum('total')),
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
