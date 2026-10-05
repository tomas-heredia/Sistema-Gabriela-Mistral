<?php

namespace App\Cobranzas\Services;

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Enums\Nivel;
use App\Alumnos\Models\Enums\Turno;
use App\Cobranzas\Models\PagoCuota;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use NumberFormatter;

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
        $cuota = $pagoCuota->cuota()->with(['alumno.tutores', 'periodoLectivo'])->first();
        $alumno = $cuota->alumno;
        $tutor = $alumno->tutores->firstWhere('pivot.responsable_pago', true) ?? $alumno->tutores->first();
        $pago = $pagoCuota->pago;

        $pdf = Pdf::loadView('pagos.comprobante', [
            'numeroRecibo' => $pagoCuota->numero_recibo,
            'fecha' => $pago->fecha,
            'tutorNombre' => $tutor->nombre,
            'tutorDni' => $tutor->dni,
            'alumnoNombre' => $alumno->nombre,
            'alumnoDni' => $alumno->dni,
            'nivel' => ucfirst($alumno->nivel->value),
            'gradoLinea' => $this->gradoLinea($alumno),
            'periodoLinea' => str_pad((string) $cuota->mes, 2, '0', STR_PAD_LEFT).'/'.$cuota->periodoLectivo->nombre,
            'formaDePago' => $pago->medio_pago->label(),
            'subtotal' => $cuota->monto_base,
            'descuento' => $cuota->descuento_monto,
            'interes' => $pagoCuota->interes_aplicado,
            'total' => $pagoCuota->montoTotal(),
            'totalEnLetras' => $this->montoEnLetras($pagoCuota->montoTotal()),
        ]);

        $rutaRelativa = "pagos/comprobantes/{$pagoCuota->id}.pdf";
        Storage::disk('local')->put($rutaRelativa, $pdf->output());

        $pagoCuota->forceFill(['pdf_path' => $rutaRelativa])->save();

        return $pagoCuota;
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

    /**
     * "SON: ..." en letras. `NumberFormatter::SPELLOUT` ya sabe números en
     * español; solo hace falta la corrección de apócope (veintiuno ->
     * veintiún) antes de "pesos" y separar pesos de centavos.
     */
    private function montoEnLetras(int $centavos): string
    {
        $pesos = intdiv($centavos, 100);
        $centavosRestantes = $centavos % 100;

        $formateador = new NumberFormatter('es', NumberFormatter::SPELLOUT);
        $pesosEnLetras = preg_replace('/uno$/', 'un', $formateador->format($pesos));

        $texto = mb_strtoupper($pesosEnLetras).' PESOS';

        if ($centavosRestantes > 0) {
            $centavosEnLetras = preg_replace('/uno$/', 'un', $formateador->format($centavosRestantes));
            $texto .= ' CON '.mb_strtoupper($centavosEnLetras).' CENTAVOS';
        }

        return $texto;
    }
}
