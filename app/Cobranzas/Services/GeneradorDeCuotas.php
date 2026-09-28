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
 * beca completa por hermano o por beca propiamente dicha. Se generan todas
 * juntas — no mes a mes — para que siempre exista una cuota futura donde
 * aplicar un pago adelantado (ver AsignadorDePagos).
 */
class GeneradorDeCuotas
{
    public function generar(Alumno $alumno, PeriodoLectivo $periodo): Collection
    {
        $arancelMatricula = $this->arancel($periodo, $alumno, TipoCuota::Matricula);
        $arancelMensualidad = $this->arancel($periodo, $alumno, TipoCuota::Mensualidad);

        $descuentoTipo = $this->descuentoAplicable($alumno, $periodo);
        $descuentoPct = $descuentoTipo === DescuentoTipo::Ninguno ? 0.0 : 100.0;

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

        return $cuotas;
    }

    private function descuentoAplicable(Alumno $alumno, PeriodoLectivo $periodo): DescuentoTipo
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
     * Promoción por hermano (pedido del cliente, reemplaza el descuento
     * porcentual anterior): no escala con la cantidad de hermanos -- es
     * siempre exactamente uno con beca completa, el 3ro matriculado en
     * orden de alta en el sistema (único orden que existe; no hay fecha de
     * nacimiento por hermano). Con 3, 4 o 6 hermanos activos que comparten
     * un tutor responsable de pago, ese 3ro no paga nada y el resto paga
     * el 100% -- nunca hay un 4to o 6to becado.
     *
     * Al ser un cálculo por orden de alta (no por orden en que se generan
     * las cuotas de cada uno), da el mismo resultado sin importar en qué
     * orden el cobrador vaya generando las cuotas de cada hermano -- no
     * hace falta recalcular retroactivamente a nadie cuando se matricula
     * un hermano nuevo.
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
