<?php

namespace App\Cobranzas\Http\Controllers;

use App\Cobranzas\Models\Pago;
use App\Cobranzas\Models\PagoCuota;
use App\Core\Http\Controllers\Controller;
use App\Core\Models\PeriodoLectivo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una fila por recibo (pago_cuota) -- desde que cada cuota paga emite su
 * propio comprobante numerado, esa es la unidad natural del reporte, no el
 * pago (que puede agrupar varios meses, y hasta de más de un alumno si son
 * hermanos). El importe de cada fila es lo efectivamente cobrado para esa
 * cuota: lo aplicado a la deuda más el interés.
 *
 * Los pagos anulados quedan afuera: este reporte es "lo efectivamente
 * cobrado", y un pago anulado ya no representa dinero recibido.
 */
class PagosPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Pago::class);

        $desde = $request->string('desde')->toString() ?: null;
        $hasta = $request->string('hasta')->toString() ?: null;
        $periodoLectivoId = $request->integer('periodo_lectivo_id') ?: null;

        $filas = PagoCuota::query()
            ->join('pagos', 'pago_cuota.pago_id', '=', 'pagos.id')
            ->join('cuotas', 'pago_cuota.cuota_id', '=', 'cuotas.id')
            ->join('alumnos', 'cuotas.alumno_id', '=', 'alumnos.id')
            ->whereNull('pagos.anulado_at')
            ->when($desde, fn ($query) => $query->where('pagos.fecha', '>=', $desde))
            ->when($hasta, fn ($query) => $query->where('pagos.fecha', '<=', $hasta))
            ->when($periodoLectivoId, fn ($query) => $query->where('cuotas.periodo_lectivo_id', $periodoLectivoId))
            ->select([
                'pagos.fecha',
                'pago_cuota.numero_recibo',
                'alumnos.dni as alumno_dni',
                'alumnos.nombre as alumno_nombre',
                'pago_cuota.monto_aplicado',
                'pago_cuota.interes_aplicado',
            ])
            ->orderBy('pagos.fecha')
            ->orderBy('pago_cuota.numero_recibo')
            ->get();

        if ($filas->isEmpty()) {
            return redirect()->route('pagos.index')->with('error', 'No hay pagos para generar el PDF con esos filtros.');
        }

        $periodo = $periodoLectivoId ? PeriodoLectivo::find($periodoLectivoId) : null;

        $pdf = Pdf::loadView('pagos.pdf', [
            'filas' => $filas,
            'desde' => $desde,
            'hasta' => $hasta,
            'periodo' => $periodo,
            'generadoEl' => now(),
        ]);

        return $pdf->download('pagos-'.now()->format('Y-m-d').'.pdf');
    }
}
