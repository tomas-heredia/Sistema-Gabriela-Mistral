<?php

namespace App\Cobranzas\Http\Controllers;

use App\Cobranzas\Models\Pago;
use App\Core\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un listado tabular de los pagos vigentes (nombre del tutor, monto, medio,
 * fecha y N° de recibo) -- la misma información que ya muestra la tabla en
 * pantalla, no un comprobante por alumno. Los pagos anulados quedan afuera:
 * este reporte es "lo efectivamente cobrado".
 */
class PagosPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Pago::class);

        $desde = $request->string('desde')->toString() ?: null;
        $hasta = $request->string('hasta')->toString() ?: null;
        $periodoLectivoId = $request->integer('periodo_lectivo_id') ?: null;

        $pagos = Pago::query()
            ->with(['tutor', 'pagoCuotas'])
            ->whereNull('anulado_at')
            ->when($desde, fn ($query) => $query->where('fecha', '>=', $desde))
            ->when($hasta, fn ($query) => $query->where('fecha', '<=', $hasta))
            ->when($periodoLectivoId, function ($query) use ($periodoLectivoId) {
                $query->whereHas('pagoCuotas.cuota', fn ($q) => $q->where('periodo_lectivo_id', $periodoLectivoId));
            })
            ->orderBy('fecha')
            ->get();

        if ($pagos->isEmpty()) {
            return redirect()->route('pagos.index')->with('error', 'No hay pagos para generar el PDF con esos filtros.');
        }

        $pdf = Pdf::loadView('pagos.pdf', [
            'pagos' => $pagos,
        ]);

        return $pdf->download('pagos-'.now()->format('Y-m-d').'.pdf');
    }
}
