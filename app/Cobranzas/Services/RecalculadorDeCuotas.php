<?php

namespace App\Cobranzas\Services;

use App\Alumnos\Models\Alumno;
use App\Cobranzas\Models\Arancel;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\DescuentoTipo;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Core\Models\PeriodoLectivo;

/**
 * Recalcula cuotas ya generadas cuando cambia algo que afectó su monto en
 * el momento de crearlas -- nunca toca una cuota que ya tenga algún pago
 * aplicado (`Parcial`/`Pagada`), ni una `Anulada`, solo `Pendiente`/`Exenta`
 * (pedido explícito del cliente: no mezclar un pago ya hecho con un monto
 * base distinto al que existía cuando se pagó esa parte).
 */
class RecalculadorDeCuotas
{
    private const ESTADOS_SIN_PAGOS = [EstadoCuota::Pendiente, EstadoCuota::Exenta];

    public function __construct(private CalculadorDeDescuento $calculadorDeDescuento) {}

    /**
     * Se revocó la beca de un alumno: las cuotas pendientes que ya habían
     * quedado en $0 por esa beca tienen que volver a cobrarse -- pero
     * siguen exentas si, sin la beca, el alumno pasa a calificar como el
     * 3er hermano (ver CalculadorDeDescuento). No toca el `monto_base`: eso
     * es responsabilidad de recalcularPorArancel(), no de este método.
     *
     * $desde: si se pasa, solo recalcula cuotas que vencen desde esa fecha
     * en adelante (inclusive). Sin fecha, recalcula todas las pendientes
     * del alumno en ese período.
     */
    public function recalcularPorBeca(Alumno $alumno, PeriodoLectivo $periodo, ?string $desde = null): void
    {
        $descuentoTipo = $this->calculadorDeDescuento->aplicable($alumno, $periodo);

        Cuota::query()
            ->where('alumno_id', $alumno->id)
            ->where('periodo_lectivo_id', $periodo->id)
            ->whereIn('estado', self::ESTADOS_SIN_PAGOS)
            ->when($desde, fn ($query) => $query->where('fecha_vencimiento', '>=', $desde))
            ->get()
            ->each(fn (Cuota $cuota) => $this->aplicar($cuota, $cuota->monto_base, $descuentoTipo));
    }

    /**
     * Cambió el monto de matrícula/mensualidad de un período ya en uso:
     * las cuotas pendientes generadas con el monto viejo toman el nuevo.
     * Mantiene el descuento que la cuota ya tenía -- revisar si sigue
     * teniendo beca o hermano es responsabilidad de recalcularPorBeca(),
     * no de este método.
     */
    public function recalcularPorArancel(Arancel $arancel): void
    {
        Cuota::query()
            ->where('periodo_lectivo_id', $arancel->periodo_lectivo_id)
            ->where('tipo', $arancel->tipo)
            ->whereIn('estado', self::ESTADOS_SIN_PAGOS)
            ->whereHas('alumno', fn ($query) => $query->where('nivel', $arancel->nivel))
            ->get()
            ->each(fn (Cuota $cuota) => $this->aplicar($cuota, $arancel->monto, $cuota->descuento_tipo));
    }

    /**
     * Misma fórmula que GeneradorDeCuotas::crearCuota() -- descuento total
     * (100%) o ninguno (0%), nunca un porcentaje intermedio.
     */
    private function aplicar(Cuota $cuota, int $montoBase, DescuentoTipo $descuentoTipo): void
    {
        $descuentoPct = $descuentoTipo === DescuentoTipo::Ninguno ? 0.0 : 100.0;
        $descuentoMonto = (int) round($montoBase * $descuentoPct / 100);
        $monto = max(0, $montoBase - $descuentoMonto);

        $cuota->update([
            'monto_base' => $montoBase,
            'descuento_tipo' => $descuentoTipo,
            'descuento_monto' => $descuentoMonto,
            'monto' => $monto,
        ]);

        $cuota->recalcularEstado();
    }
}
