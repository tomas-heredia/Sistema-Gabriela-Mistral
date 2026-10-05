<?php

namespace App\Cobranzas\Services;

use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\TipoCuota;

/**
 * La matrícula no es un mes real (`cuota.mes` vale 0 solo para que el
 * índice único de `cuotas` no choque entre matrículas -- ver su migración),
 * así que mostrar "00/2026" en el comprobante es ruido sin sentido. Para
 * mensualidad sí corresponde mes/año.
 */
class PeriodoDeCuotaEnTexto
{
    public static function calcular(Cuota $cuota): string
    {
        if ($cuota->tipo === TipoCuota::Matricula) {
            return "Matrícula {$cuota->periodoLectivo->nombre}";
        }

        return str_pad((string) $cuota->mes, 2, '0', STR_PAD_LEFT).'/'.$cuota->periodoLectivo->nombre;
    }
}
