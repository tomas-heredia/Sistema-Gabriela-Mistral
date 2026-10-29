<?php

namespace App\Boletines\Http\Controllers;

use App\Boletines\Models\BoletinTrimestre;
use App\Core\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Una página mínima con el PDF en un iframe que se manda a imprimir solo al
 * cargar -- igual razón que ImprimirComprobantePagoController (Cobranzas):
 * abrir el PDF directo y confiar en que el navegador dispare su propio
 * diálogo de impresión no es consistente entre navegadores.
 */
class ImprimirBoletinPdfController extends Controller
{
    public function __invoke(BoletinTrimestre $boletinTrimestre): View
    {
        Gate::authorize('view', $boletinTrimestre->boletin);

        if (! $boletinTrimestre->pdf_path || ! Storage::disk('local')->exists($boletinTrimestre->pdf_path)) {
            abort(404);
        }

        return view('boletines.imprimir', [
            'urlPdf' => route('boletines.trimestres.pdf.ver', $boletinTrimestre),
        ]);
    }
}
