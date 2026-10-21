<?php

namespace App\Cobranzas\Services;

use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\MovimientoSaldoAFavor;

/**
 * Igual filosofía que CalculadorDeDeuda: el saldo disponible se calcula al
 * momento, nunca se guarda en una columna cacheada -- así un pago anulado
 * (ver Pago::anular()) deja de contar en el acto, sin tener que recalcular
 * nada a mano en otro lado.
 */
class CalculadorDeSaldoAFavor
{
    public function calcular(Tutor $tutor): int
    {
        return (int) MovimientoSaldoAFavor::query()
            ->where('tutor_id', $tutor->id)
            ->whereHas('pago', fn ($query) => $query->whereNull('anulado_at'))
            ->sum('monto');
    }
}
