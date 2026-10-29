<?php

namespace App\Boletines\Http\Controllers;

use App\Boletines\Models\BoletinTrimestre;
use App\Core\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Igual que BoletinPdfController pero con Content-Disposition inline en vez
 * de attachment -- el navegador lo muestra con su visor de PDF en vez de
 * forzar la descarga. Lo usa el iframe de ImprimirBoletinPdfController.
 */
class VerBoletinPdfController extends Controller
{
    public function __invoke(BoletinTrimestre $boletinTrimestre): Response
    {
        Gate::authorize('view', $boletinTrimestre->boletin);

        if (! $boletinTrimestre->pdf_path || ! Storage::disk('local')->exists($boletinTrimestre->pdf_path)) {
            abort(404);
        }

        $nombreArchivo = $boletinTrimestre->boletin->alumno->nombre.' - Trimestre '.$boletinTrimestre->trimestre.'.pdf';

        return Storage::disk('local')->response($boletinTrimestre->pdf_path, $nombreArchivo);
    }
}
