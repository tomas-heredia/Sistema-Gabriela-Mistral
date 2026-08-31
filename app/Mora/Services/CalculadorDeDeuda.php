<?php

namespace App\Mora\Services;

use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\TipoCuota;

class CalculadorDeDeuda
{
    /**
     * Deuda actual de un tutor: cuotas vencidas y no saldadas (pendiente o
     * parcial) de los alumnos donde es responsable de pago. La matrícula
     * impaga suma al monto pero no cuenta como "mes" adeudado.
     *
     * @return array{monto_adeudado: int, meses_adeudados: int}
     */
    public function calcular(Tutor $tutor): array
    {
        $alumnoIds = $tutor->alumnos()
            ->wherePivot('responsable_pago', true)
            ->pluck('alumnos.id');

        if ($alumnoIds->isEmpty()) {
            return ['monto_adeudado' => 0, 'meses_adeudados' => 0];
        }

        $cuotasEnMora = Cuota::query()
            ->whereIn('alumno_id', $alumnoIds)
            ->whereIn('estado', [EstadoCuota::Pendiente, EstadoCuota::Parcial])
            ->where('fecha_vencimiento', '<', today())
            ->get();

        $montoAdeudado = $cuotasEnMora->sum(fn (Cuota $cuota) => $cuota->monto - $cuota->montoPagado());
        $mesesAdeudados = $cuotasEnMora->where('tipo', TipoCuota::Mensualidad)->count();

        return [
            'monto_adeudado' => (int) $montoAdeudado,
            'meses_adeudados' => $mesesAdeudados,
        ];
    }
}
