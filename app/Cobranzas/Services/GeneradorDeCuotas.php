<?php

namespace App\Cobranzas\Services;

use App\Alumnos\Models\Alumno;
use App\Cobranzas\Models\Arancel;
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
    public function __construct(private CalculadorDeDescuento $calculadorDeDescuento) {}

    public function generar(Alumno $alumno, PeriodoLectivo $periodo): Collection
    {
        $arancelMatricula = $this->arancel($periodo, $alumno, TipoCuota::Matricula);
        $arancelMensualidad = $this->arancel($periodo, $alumno, TipoCuota::Mensualidad);

        $descuentoTipo = $this->calculadorDeDescuento->aplicable($alumno, $periodo);
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
