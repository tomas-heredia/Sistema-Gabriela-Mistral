<?php

namespace App\Mora\Services;

use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\TipoCuota;
use Illuminate\Support\Collection;

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

    /**
     * Igual que calcular(), pero para todos los tutores a la vez -- una
     * sola pasada por las cuotas vencidas en vez de una consulta por
     * tutor. Pensado para la pantalla de morosos: siempre calculado en el
     * momento (nunca desde una tabla que haya que ir actualizando a mano),
     * así que apenas se generan cuotas nuevas para un alumno, su tutor
     * aparece o desaparece solo de esta lista la próxima vez que se mire.
     *
     * $desde/$hasta filtran por fecha de vencimiento de la cuota (no por
     * período lectivo). $busqueda filtra por nombre o DNI del tutor.
     *
     * @return Collection<int, array{tutor: Tutor, monto_adeudado: int, meses_adeudados: int}>
     */
    public function tutoresEnMora(?string $desde = null, ?string $hasta = null, string $busqueda = ''): Collection
    {
        $cuotasEnMora = Cuota::query()
            ->whereIn('estado', [EstadoCuota::Pendiente, EstadoCuota::Parcial])
            ->where('fecha_vencimiento', '<', today())
            ->when($desde, fn ($query) => $query->where('fecha_vencimiento', '>=', $desde))
            ->when($hasta, fn ($query) => $query->where('fecha_vencimiento', '<=', $hasta))
            ->with('alumno.tutores')
            ->get();

        $porTutor = collect();

        foreach ($cuotasEnMora as $cuota) {
            $saldo = $cuota->monto - $cuota->montoPagado();

            if ($saldo <= 0) {
                continue;
            }

            $responsables = $cuota->alumno->tutores->filter(fn (Tutor $tutor) => $tutor->pivot->responsable_pago);

            foreach ($responsables as $tutor) {
                $fila = $porTutor->get($tutor->id) ?? ['tutor' => $tutor, 'monto_adeudado' => 0, 'meses_adeudados' => 0];
                $fila['monto_adeudado'] += $saldo;

                if ($cuota->tipo === TipoCuota::Mensualidad) {
                    $fila['meses_adeudados']++;
                }

                $porTutor->put($tutor->id, $fila);
            }
        }

        $resultado = $porTutor->values()->sortByDesc('monto_adeudado')->values();

        if ($busqueda === '') {
            return $resultado;
        }

        $busquedaNormalizada = mb_strtolower($busqueda);

        return $resultado->filter(
            fn (array $fila) => str_contains(mb_strtolower($fila['tutor']->nombre), $busquedaNormalizada)
                || str_contains(mb_strtolower($fila['tutor']->dni), $busquedaNormalizada)
        )->values();
    }
}
