<?php

namespace App\Cobranzas\Services;

use App\Alumnos\Models\Alumno;
use App\Cobranzas\Models\Beca;
use App\Cobranzas\Models\Enums\DescuentoTipo;
use App\Core\Models\PeriodoLectivo;

/**
 * Qué descuento le corresponde a un alumno en un período: beca propia, o
 * promoción por ser el 3er hermano matriculado (nunca ambos a la vez -- la
 * beca individual gana si ya existe, ver GeneradorDeCuotasTest). Se extrajo
 * de GeneradorDeCuotas para poder reusar la misma regla desde
 * RecalculadorDeCuotas (recalcular tras revocar una beca, sin duplicar la
 * lógica de "3er hermano").
 */
class CalculadorDeDescuento
{
    public function aplicable(Alumno $alumno, PeriodoLectivo $periodo): DescuentoTipo
    {
        $tieneBeca = Beca::query()
            ->where('alumno_id', $alumno->id)
            ->where('periodo_lectivo_id', $periodo->id)
            ->exists();

        if ($tieneBeca) {
            return DescuentoTipo::Beca;
        }

        return $this->esElTercerHermano($alumno) ? DescuentoTipo::Hermanos : DescuentoTipo::Ninguno;
    }

    /**
     * Promoción por hermano: no escala con la cantidad de hermanos -- es
     * siempre exactamente uno con beca completa, el 3ro matriculado en
     * orden de alta en el sistema (único orden que existe; no hay fecha de
     * nacimiento por hermano). Ver GeneradorDeCuotas, de donde se extrajo
     * este método, para el detalle completo del razonamiento.
     */
    private function esElTercerHermano(Alumno $alumno): bool
    {
        $tutorIds = $alumno->tutores()
            ->wherePivot('responsable_pago', true)
            ->pluck('tutores.id');

        if ($tutorIds->isEmpty()) {
            return false;
        }

        $hermanos = Alumno::query()
            ->where('activo', true)
            ->whereHas('tutores', function ($query) use ($tutorIds) {
                // wherePivot() no existe acá: el closure de whereHas recibe
                // un query builder sobre el modelo relacionado con el join a
                // la tabla pivote ya aplicado, no la relación BelongsToMany.
                $query->whereIn('tutores.id', $tutorIds)->where('alumno_tutor.responsable_pago', true);
            })
            ->orderBy('id')
            ->pluck('id');

        return $hermanos->count() >= 3 && $hermanos->get(2) === $alumno->id;
    }
}
