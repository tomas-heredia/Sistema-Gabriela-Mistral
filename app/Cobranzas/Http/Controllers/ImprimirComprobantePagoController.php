<?php

namespace App\Cobranzas\Http\Controllers;

use App\Cobranzas\Models\PagoCuota;
use App\Core\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Una página mínima con el PDF en un iframe que se manda a imprimir solo
 * al cargar -- abrir el PDF directo y confiar en que el navegador dispare
 * su propio diálogo de impresión no es consistente entre navegadores.
 */
class ImprimirComprobantePagoController extends Controller
{
    public function __invoke(string $numeroRecibo): View
    {
        $pagoCuota = PagoCuota::where('numero_recibo', $numeroRecibo)->with('pago')->first();

        if (! $pagoCuota) {
            abort(404);
        }

        Gate::authorize('view', $pagoCuota->pago);

        if (! $pagoCuota->pdf_path || ! Storage::disk('local')->exists($pagoCuota->pdf_path)) {
            abort(404);
        }

        return view('pagos.imprimir', [
            'urlPdf' => route('pagos.comprobantes.ver', $numeroRecibo),
        ]);
    }
}
