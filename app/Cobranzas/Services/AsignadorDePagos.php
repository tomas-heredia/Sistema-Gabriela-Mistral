<?php

namespace App\Cobranzas\Services;

use App\Cobranzas\Exceptions\AsignacionDePagoInvalidaException;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\MovimientoSaldoAFavor;
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
 * para `Cuota::montoPagado()` ni su saldo pendiente).
 *
 * El número de recibo se numera por alumno, no por cuota: si este pago
 * cubre varios meses del mismo alumno, todas esas filas de pago_cuota
 * comparten un único numero_recibo (un solo comprobante que los junta,
 * ver GeneradorDeComprobantePago). Si además cubre a un hermano, ese
 * hermano se numera aparte -- cada alumno tiene su propio comprobante.
 */
class AsignadorDePagos
{
    public function __construct(
        private CalculadorDeSaldoAFavor $calculadorDeSaldo,
    ) {}

    /**
     * $saldoAFavorGenerado: lo que el tutor entregó de más, para dejar como
     * saldo a favor a futuro. $saldoAFavorConsumido: cuánto de su saldo a
     * favor existente se descuenta de este pago (el tutor entrega menos
     * efectivo porque ya tenía a favor). El efectivo que realmente entra
     * (Pago::monto) es lo aplicado a cuotas, menos lo consumido, más lo
     * generado -- ver la cuenta más abajo.
     *
     * @param  array<int, array{monto: int, interes: int}>  $asignaciones  [cuota_id => ['monto' => monto_aplicado, 'interes' => interes_aplicado]]
     */
    public function aplicar(Pago $pago, array $asignaciones, int $saldoAFavorGenerado = 0, int $saldoAFavorConsumido = 0): void
    {
        if ($pago->estaAnulado()) {
            throw new AsignacionDePagoInvalidaException('No se puede aplicar un pago anulado.');
        }

        if (empty($asignaciones)) {
            throw new AsignacionDePagoInvalidaException('No hay cuotas para aplicar.');
        }

        if ($saldoAFavorGenerado < 0 || $saldoAFavorConsumido < 0) {
            throw new AsignacionDePagoInvalidaException('El saldo a favor a dejar o a usar no puede ser negativo.');
        }

        $sumaTotal = array_sum(array_map(fn ($a) => $a['monto'] + $a['interes'], $asignaciones));
        $montoEsperado = $sumaTotal - $saldoAFavorConsumido + $saldoAFavorGenerado;

        if ($montoEsperado !== $pago->monto) {
            throw new AsignacionDePagoInvalidaException(
                "La suma de las asignaciones ({$sumaTotal}), descontando el saldo a favor usado ({$saldoAFavorConsumido}) y sumando el que se deja ({$saldoAFavorGenerado}), no coincide con el monto del pago ({$pago->monto}). No puede quedar plata sin aplicar."
            );
        }

        if ($saldoAFavorConsumido > 0) {
            $saldoDisponible = $this->calculadorDeSaldo->calcular($pago->tutor);

            if ($saldoAFavorConsumido > $saldoDisponible) {
                throw new AsignacionDePagoInvalidaException(
                    "El tutor quiere usar {$saldoAFavorConsumido} de saldo a favor, pero solo tiene {$saldoDisponible} disponible."
                );
            }
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

        DB::transaction(function () use ($pago, $asignaciones, $cuotas, $saldoAFavorGenerado, $saldoAFavorConsumido) {
            $cuotaIdsPorAlumno = [];

            foreach ($asignaciones as $cuotaId => $datos) {
                $cuotaIdsPorAlumno[$cuotas->get($cuotaId)->alumno_id][] = $cuotaId;
            }

            foreach ($cuotaIdsPorAlumno as $cuotaIdsDelAlumno) {
                $numeroRecibo = $this->siguienteNumeroRecibo();

                foreach ($cuotaIdsDelAlumno as $cuotaId) {
                    $datos = $asignaciones[$cuotaId];

                    PagoCuota::create([
                        'pago_id' => $pago->id,
                        'cuota_id' => $cuotaId,
                        'monto_aplicado' => $datos['monto'],
                        'interes_aplicado' => $datos['interes'],
                        'numero_recibo' => $numeroRecibo,
                    ]);
                }
            }

            if ($saldoAFavorGenerado > 0) {
                MovimientoSaldoAFavor::create([
                    'tutor_id' => $pago->tutor_id,
                    'pago_id' => $pago->id,
                    'monto' => $saldoAFavorGenerado,
                ]);
            }

            if ($saldoAFavorConsumido > 0) {
                MovimientoSaldoAFavor::create([
                    'tutor_id' => $pago->tutor_id,
                    'pago_id' => $pago->id,
                    'monto' => -$saldoAFavorConsumido,
                ]);
            }
        });
    }

    /**
     * Correlativo con ceros a la izquierda (000001, 000002…) -- uno por
     * alumno dentro de la operación, no uno por cuota ni uno por pago. El
     * lock se vuelve a tomar en cada vuelta del loop de `aplicar()` para que
     * dos alumnos de la misma operación nunca salgan con el mismo número.
     */
    private function siguienteNumeroRecibo(): string
    {
        $maximo = (int) (PagoCuota::query()->lockForUpdate()->max(DB::raw('CAST(numero_recibo AS UNSIGNED)')) ?? 0);

        return str_pad((string) ($maximo + 1), 6, '0', STR_PAD_LEFT);
    }
}
