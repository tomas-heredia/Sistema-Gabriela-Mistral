<?php

namespace App\Boletines\Http\Controllers;

use App\Boletines\Models\BoletinTrimestre;
use App\Core\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descarga el PDF ya generado de un trimestre (solo existe una vez que el
 * trimestre está `enviado` -- ver GenerarYEnviarBoletinPdf). Un borrador
 * `pendiente`/`cargado` no tiene pdf_path todavía.
 */
class BoletinPdfController extends Controller
{
    public function __invoke(BoletinTrimestre $boletinTrimestre): Response
    {
        Gate::authorize('view', $boletinTrimestre->boletin);

        if (! $boletinTrimestre->pdf_path || ! Storage::disk('local')->exists($boletinTrimestre->pdf_path)) {
            abort(404);
        }

        $nombreArchivo = Str::slug($boletinTrimestre->boletin->alumno->nombre).'-trimestre-'.$boletinTrimestre->trimestre.'.pdf';

        return Storage::disk('local')->download($boletinTrimestre->pdf_path, $nombreArchivo);
    }
}
