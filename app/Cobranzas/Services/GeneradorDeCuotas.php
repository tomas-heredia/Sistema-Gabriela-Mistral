<?php

namespace App\Cobranzas\Services;

use App\Alumnos\Models\Alumno;
use App\Cobranzas\Models\Arancel;
use App\Cobranzas\Models\Beca;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\DescuentoTipo;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\TipoCuota;
use App\Core\Models\PeriodoLectivo;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Genera de una sola vez todas las cuotas de un alumno para un período
 * lectivo (matrícula + una mensualidad por cada mes del período), aplicando
 * descuento por hermanos o exención por beca. Se generan todas juntas — no
 * mes a mes — para que siempre exista una cuota futura donde aplicar un
 * pago adelantado (ver AsignadorDePagos).
 */
class GeneradorDeCuotas
{
    /**
     * Umbral a partir del cual se activa el descuento por hermanos: con 1 o 2
     * hermanos matriculados no hay descuento; al llegar a 3 se aplica a los 3
     * (o más), incluidos el 1º y 2º.
     */
    private const UMBRAL_HERMANOS = 3;

    public function generar(Alumno $alumno, PeriodoLectivo $periodo): Collection
    {
        $arancelMatricula = $this->arancel($periodo, $alumno, TipoCuota::Matricula);
        $arancelMensualidad = $this->arancel($periodo, $alumno, TipoCuota::Mensualidad);

        [$descuentoTipo, $descuentoPct] = $this->descuentoAplicable($alumno, $periodo);

        $cuotas = collect();

        $cuotas->push($this->crearCuota(
            alumno: $alumno,
            periodo: $periodo,
            tipo: TipoCuota::Matricula,
            mes: 0,
            montoBase: $arancelMatricula->monto,
            descuentoTipo: $descuentoTipo,
            descuentoPct: $descuentoPct,
            fechaVencimiento: $periodo->fecha_inicio->copy(),
        ));

        foreach ($this->mesesDelPeriodo($periodo) as $fechaMes) {
            $cuotas->push($this->crearCuota(
                alumno: $alumno,
                periodo: $periodo,
                tipo: TipoCuota::Mensualidad,
                mes: (int) $fechaMes->format('n'),
                montoBase: $arancelMensualidad->monto,
                descuentoTipo: $descuentoTipo,
                descuentoPct: $descuentoPct,
                fechaVencimiento: $fechaMes->copy()->day(10),
            ));
        }

        if ($descuentoTipo === DescuentoTipo::Hermanos) {
            $this->actualizarHermanosYaMatriculados($alumno, $periodo);
        }

        return $cuotas;
    }

    /**
     * Al matricular al alumno que hace cruzar el umbral, los hermanos que ya
     * estaban cargados quedaron con cuotas generadas *antes* de llegar a 3 --
     * su descuento_tipo quedó fijo en "ninguno" para siempre, aunque ahora sí
     * corresponda. Se corrige hacia adelante: solo las cuotas del mismo
     * período que todavía no vencieron (una cuota ya vencida es un monto ya
     * facturado, no se toca retroactivamente — mismo criterio que el resto
     * del sistema). Un hermano con beca no se toca: la beca sigue ganando.
     */
    private function actualizarHermanosYaMatriculados(Alumno $alumnoRecienMatriculado, PeriodoLectivo $periodo): void
    {
        $tutorIds = $alumnoRecienMatriculado->tutores()
            ->wherePivot('responsable_pago', true)
            ->pluck('tutores.id');

        $hermanos = Alumno::query()
            ->where('id', '!=', $alumnoRecienMatriculado->id)
            ->where('activo', true)
            ->whereHas('tutores', function ($query) use ($tutorIds) {
                $query->whereIn('tutores.id', $tutorIds)->where('alumno_tutor.responsable_pago', true);
            })
            ->get();

        foreach ($hermanos as $hermano) {
            [$descuentoTipo, $descuentoPct] = $this->descuentoAplicable($hermano, $periodo);

            if ($descuentoTipo !== DescuentoTipo::Hermanos) {
                continue;
            }

            Cuota::query()
                ->where('alumno_id', $hermano->id)
                ->where('periodo_lectivo_id', $periodo->id)
                ->where('fecha_vencimiento', '>=', today())
                ->where('estado', '!=', EstadoCuota::Anulada)
                ->get()
                ->each(function (Cuota $cuota) use ($descuentoTipo, $descuentoPct) {
                    $descuentoMonto = (int) round($cuota->monto_base * $descuentoPct / 100);

                    $cuota->forceFill([
                        'descuento_tipo' => $descuentoTipo,
                        'descuento_monto' => $descuentoMonto,
                        'monto' => max(0, $cuota->monto_base - $descuentoMonto),
                    ])->save();

                    $cuota->recalcularEstado();
                });
        }
    }

    /**
     * Hermanos matriculados = alumnos activos que comparten con este alumno
     * un tutor marcado `responsable_pago`, contándose a sí mismo. Si el
     * alumno no tiene tutor responsable de pago, nunca puede llegar al
     * umbral (queda solo).
     */
    public function hermanosMatriculados(Alumno $alumno): int
    {
        $tutorIds = $alumno->tutores()
            ->wherePivot('responsable_pago', true)
            ->pluck('tutores.id');

        if ($tutorIds->isEmpty()) {
            return 1;
        }

        return Alumno::query()
            ->where('activo', true)
            ->whereHas('tutores', function ($query) use ($tutorIds) {
                // wherePivot() no existe acá: el closure de whereHas recibe
                // un query builder sobre el modelo relacionado con el join a
                // la tabla pivote ya aplicado, no la relación BelongsToMany.
                $query->whereIn('tutores.id', $tutorIds)->where('alumno_tutor.responsable_pago', true);
            })
            ->count();
    }

    /**
     * @return array{0: DescuentoTipo, 1: float}
     */
    private function descuentoAplicable(Alumno $alumno, PeriodoLectivo $periodo): array
    {
        $tieneBeca = Beca::query()
            ->where('alumno_id', $alumno->id)
            ->where('periodo_lectivo_id', $periodo->id)
            ->exists();

        if ($tieneBeca) {
            return [DescuentoTipo::Beca, 100.0];
        }

        if ($this->hermanosMatriculados($alumno) >= self::UMBRAL_HERMANOS) {
            return [DescuentoTipo::Hermanos, (float) $periodo->descuento_hermanos_pct];
        }

        return [DescuentoTipo::Ninguno, 0.0];
    }

    private function crearCuota(
        Alumno $alumno,
        PeriodoLectivo $periodo,
        TipoCuota $tipo,
        int $mes,
        int $montoBase,
        DescuentoTipo $descuentoTipo,
        float $descuentoPct,
        Carbon $fechaVencimiento,
    ): Cuota {
        $descuentoMonto = (int) round($montoBase * $descuentoPct / 100);
        $monto = max(0, $montoBase - $descuentoMonto);

        return Cuota::create([
            'alumno_id' => $alumno->id,
            'periodo_lectivo_id' => $periodo->id,
            'tipo' => $tipo,
            'mes' => $mes,
            'monto_base' => $montoBase,
            'descuento_tipo' => $descuentoTipo,
            'descuento_monto' => $descuentoMonto,
            'monto' => $monto,
            'fecha_vencimiento' => $fechaVencimiento,
            'estado' => $monto === 0 ? EstadoCuota::Exenta : EstadoCuota::Pendiente,
        ]);
    }

    private function arancel(PeriodoLectivo $periodo, Alumno $alumno, TipoCuota $tipo): Arancel
    {
        return Arancel::query()
            ->where('periodo_lectivo_id', $periodo->id)
            ->where('nivel', $alumno->nivel)
            ->where('tipo', $tipo)
            ->firstOrFail();
    }

    private function mesesDelPeriodo(PeriodoLectivo $periodo): CarbonPeriod
    {
        return CarbonPeriod::create(
            $periodo->fecha_inicio->copy()->startOfMonth(),
            '1 month',
            $periodo->fecha_fin->copy()->startOfMonth(),
        );
    }
}
