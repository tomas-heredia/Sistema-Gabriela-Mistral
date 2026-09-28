<?php

namespace App\Cobranzas\Http\Controllers;

use App\Cobranzas\Models\Pago;
use App\Cobranzas\Models\PagoCuota;
use App\Core\Http\Controllers\Controller;
use App\Core\Models\PeriodoLectivo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una fila por (pago, alumno) -- un pago puede cubrir cuotas de más de un
 * alumno del mismo tutor (hermanos), y el reporte pide DNI y nombre del
 * alumno por fila, no del tutor. El importe de cada fila es lo aplicado a
 * ese alumno dentro de ese pago, no el total del pago.
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
                'pagos.id as pago_id',
                'pagos.fecha',
                'pagos.numero_recibo',
                'alumnos.dni as alumno_dni',
                'alumnos.nombre as alumno_nombre',
                DB::raw('SUM(pago_cuota.monto_aplicado) as importe'),
            ])
            ->groupBy('pagos.id', 'pagos.fecha', 'pagos.numero_recibo', 'alumnos.id', 'alumnos.dni', 'alumnos.nombre')
            ->orderBy('pagos.fecha')
            ->orderBy('pagos.numero_recibo')
            ->get();

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
