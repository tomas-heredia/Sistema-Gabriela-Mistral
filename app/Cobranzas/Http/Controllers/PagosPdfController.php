<?php

namespace App\Cobranzas\Http\Controllers;

use App\Cobranzas\Models\Pago;
use App\Cobranzas\Models\PagoCuota;
use App\Cobranzas\Services\GeneradorDeComprobantePago;
use App\Core\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una recopilación, en un solo PDF, del recibo de cada cuota pagada en el
 * período -- no un resumen tabular, sino los mismos comprobantes que recibe
 * cada tutor, uno por página, en el orden en que se cobraron.
 *
 * Los pagos anulados quedan afuera: este reporte es "lo efectivamente
 * cobrado", y un pago anulado ya no representa dinero recibido.
 */
class PagosPdfController extends Controller
{
    public function __invoke(Request $request, GeneradorDeComprobantePago $generador): Response
    {
        Gate::authorize('viewAny', Pago::class);

        $desde = $request->string('desde')->toString() ?: null;
        $hasta = $request->string('hasta')->toString() ?: null;
        $periodoLectivoId = $request->integer('periodo_lectivo_id') ?: null;

        $idsEnOrden = PagoCuota::query()
            ->join('pagos', 'pago_cuota.pago_id', '=', 'pagos.id')
            ->join('cuotas', 'pago_cuota.cuota_id', '=', 'cuotas.id')
            ->whereNull('pagos.anulado_at')
            ->when($desde, fn ($query) => $query->where('pagos.fecha', '>=', $desde))
            ->when($hasta, fn ($query) => $query->where('pagos.fecha', '<=', $hasta))
            ->when($periodoLectivoId, fn ($query) => $query->where('cuotas.periodo_lectivo_id', $periodoLectivoId))
            ->orderBy('pagos.fecha')
            ->orderBy('pago_cuota.numero_recibo')
            ->pluck('pago_cuota.id');

        if ($idsEnOrden->isEmpty()) {
            return redirect()->route('pagos.index')->with('error', 'No hay pagos para generar el PDF con esos filtros.');
        }

        $pagoCuotas = PagoCuota::with(['pago', 'cuota.alumno.tutores', 'cuota.periodoLectivo'])
            ->whereIn('id', $idsEnOrden)
            ->get()
            ->sortBy(fn (PagoCuota $pagoCuota) => $idsEnOrden->search($pagoCuota->id));

        $recibos = $pagoCuotas->map(fn (PagoCuota $pagoCuota) => $generador->datos($pagoCuota));

        $pdf = Pdf::loadView('pagos.pdf', [
            'recibos' => $recibos,
        ]);

        return $pdf->download('recibos-'.now()->format('Y-m-d').'.pdf');
    }
}
