<?php

namespace App\Cobranzas\Services;

use App\Cobranzas\Exceptions\AsignacionDePagoInvalidaException;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Pago;
use App\Cobranzas\Models\PagoCuota;
use Illuminate\Support\Facades\DB;

/**
 * Reparte un pago entre una o más cuotas. Resuelve, con la misma pieza de
 * código, tres casos: pago parcial (una cuota, monto menor a lo que cuesta),
 * pago que cubre más de un mes (varias filas de pago_cuota) y pago
 * adelantado (se aplica contra una cuota futura, que ya existe porque el
 * año se generó de una vez con GeneradorDeCuotas).
 */
class AsignadorDePagos
{
    /**
     * @param  array<int, int>  $asignaciones  [cuota_id => monto_aplicado]
     */
    public function aplicar(Pago $pago, array $asignaciones): void
    {
        if ($pago->estaAnulado()) {
            throw new AsignacionDePagoInvalidaException('No se puede aplicar un pago anulado.');
        }

        if (empty($asignaciones)) {
            throw new AsignacionDePagoInvalidaException('No hay cuotas para aplicar.');
        }

        $suma = array_sum($asignaciones);

        if ($suma !== $pago->monto) {
            throw new AsignacionDePagoInvalidaException(
                "La suma de las asignaciones ({$suma}) no coincide con el monto del pago ({$pago->monto}). No puede quedar plata sin aplicar."
            );
        }

        $cuotas = Cuota::query()->whereIn('id', array_keys($asignaciones))->get()->keyBy('id');

        foreach ($asignaciones as $cuotaId => $montoAplicado) {
            $cuota = $cuotas->get($cuotaId);

            if (! $cuota) {
                throw new AsignacionDePagoInvalidaException("La cuota {$cuotaId} no existe.");
            }

            if ($montoAplicado <= 0) {
                throw new AsignacionDePagoInvalidaException("El monto aplicado a la cuota {$cuotaId} debe ser mayor a cero.");
            }

            $saldoPendiente = $cuota->monto - $cuota->montoPagado();

            if ($montoAplicado > $saldoPendiente) {
                throw new AsignacionDePagoInvalidaException(
                    "El monto aplicado a la cuota {$cuotaId} ({$montoAplicado}) supera su saldo pendiente ({$saldoPendiente})."
                );
            }
        }

        DB::transaction(function () use ($pago, $asignaciones) {
            foreach ($asignaciones as $cuotaId => $montoAplicado) {
                PagoCuota::create([
                    'pago_id' => $pago->id,
                    'cuota_id' => $cuotaId,
                    'monto_aplicado' => $montoAplicado,
                ]);
            }
        });
    }
}
