<?php

namespace App\Cobranzas\Http\Controllers;

use App\Cobranzas\Models\PagoCuota;
use App\Core\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Igual que ComprobantePagoController pero con Content-Disposition inline
 * en vez de attachment -- el navegador lo muestra con su visor de PDF en
 * vez de forzar la descarga. Lo usa el iframe de ImprimirComprobantePagoController.
 */
class VerComprobantePagoController extends Controller
{
    public function __invoke(string $numeroRecibo): Response
    {
        $pagoCuota = PagoCuota::where('numero_recibo', $numeroRecibo)->with('pago')->first();

        if (! $pagoCuota) {
            abort(404);
        }

        Gate::authorize('view', $pagoCuota->pago);

        if (! $pagoCuota->pdf_path || ! Storage::disk('local')->exists($pagoCuota->pdf_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($pagoCuota->pdf_path, "recibo-{$numeroRecibo}.pdf");
    }
}
