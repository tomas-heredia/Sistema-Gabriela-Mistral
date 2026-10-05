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
 *
 * El interés es un recargo aparte de la deuda de la cuota (nunca cuenta
 * para `Cuota::montoPagado()` ni su saldo pendiente) -- cada cuota paga
 * emite su propio comprobante, así que también se numera por separado,
 * igual que un talonario de recibos de papel.
 */
class AsignadorDePagos
{
    /**
     * @param  array<int, array{monto: int, interes: int}>  $asignaciones  [cuota_id => ['monto' => monto_aplicado, 'interes' => interes_aplicado]]
     */
    public function aplicar(Pago $pago, array $asignaciones): void
    {
        if ($pago->estaAnulado()) {
            throw new AsignacionDePagoInvalidaException('No se puede aplicar un pago anulado.');
        }

        if (empty($asignaciones)) {
            throw new AsignacionDePagoInvalidaException('No hay cuotas para aplicar.');
        }

        $sumaTotal = array_sum(array_map(fn ($a) => $a['monto'] + $a['interes'], $asignaciones));

        if ($sumaTotal !== $pago->monto) {
            throw new AsignacionDePagoInvalidaException(
                "La suma de las asignaciones ({$sumaTotal}) no coincide con el monto del pago ({$pago->monto}). No puede quedar plata sin aplicar."
            );
        }

        $cuotas = Cuota::query()->whereIn('id', array_keys($asignaciones))->get()->keyBy('id');

        foreach ($asignaciones as $cuotaId => $datos) {
            $cuota = $cuotas->get($cuotaId);

            if (! $cuota) {
                throw new AsignacionDePagoInvalidaException("La cuota {$cuotaId} no existe.");
            }

            if ($datos['monto'] <= 0) {
                throw new AsignacionDePagoInvalidaException("El monto aplicado a la cuota {$cuotaId} debe ser mayor a cero.");
            }

            if ($datos['interes'] < 0) {
                throw new AsignacionDePagoInvalidaException("El interés aplicado a la cuota {$cuotaId} no puede ser negativo.");
            }

            $saldoPendiente = $cuota->monto - $cuota->montoPagado();

            if ($datos['monto'] > $saldoPendiente) {
                throw new AsignacionDePagoInvalidaException(
                    "El monto aplicado a la cuota {$cuotaId} ({$datos['monto']}) supera su saldo pendiente ({$saldoPendiente})."
                );
            }
        }

        DB::transaction(function () use ($pago, $asignaciones) {
            foreach ($asignaciones as $cuotaId => $datos) {
                PagoCuota::create([
                    'pago_id' => $pago->id,
                    'cuota_id' => $cuotaId,
                    'monto_aplicado' => $datos['monto'],
                    'interes_aplicado' => $datos['interes'],
                    'numero_recibo' => $this->siguienteNumeroRecibo(),
                ]);
            }
        });
    }

    /**
     * Correlativo con ceros a la izquierda (000001, 000002…), igual que el
     * que ya usaba `Pago.numero_recibo` -- ahora uno por cuota en vez de uno
     * por operación de pago. El lock se vuelve a tomar en cada vuelta del
     * loop de `aplicar()` para que dos recibos de la misma operación nunca
     * salgan con el mismo número.
     */
    private function siguienteNumeroRecibo(): string
    {
        $maximo = (int) (PagoCuota::query()->lockForUpdate()->max(DB::raw('CAST(numero_recibo AS UNSIGNED)')) ?? 0);

        return str_pad((string) ($maximo + 1), 6, '0', STR_PAD_LEFT);
    }
}
