<?php

namespace App\Cobranzas\Http\Controllers;

use App\Cobranzas\Models\PagoCuota;
use App\Core\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ComprobantePagoController extends Controller
{
    public function __invoke(PagoCuota $pagoCuota): Response
    {
        Gate::authorize('view', $pagoCuota->pago);

        if (! $pagoCuota->pdf_path || ! Storage::disk('local')->exists($pagoCuota->pdf_path)) {
            abort(404);
        }

        return Storage::disk('local')->download($pagoCuota->pdf_path, "recibo-{$pagoCuota->numero_recibo}.pdf");
    }
}
