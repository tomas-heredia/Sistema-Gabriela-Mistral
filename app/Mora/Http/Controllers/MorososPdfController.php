<?php

namespace App\Mora\Http\Controllers;

use App\Core\Http\Controllers\Controller;
use App\Core\Models\PeriodoLectivo;
use App\Mora\Models\NotificacionMora;
use App\Mora\Services\CalculadorDeDeuda;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mismo filtrado que la pantalla (busqueda + periodo, recibidos por query
 * string) -- el PDF es "lo que estás viendo", no una lista aparte.
 */
class MorososPdfController extends Controller
{
    public function __invoke(Request $request, CalculadorDeDeuda $calculador): Response
    {
        Gate::authorize('viewAny', NotificacionMora::class);

        $periodoLectivoId = $request->integer('periodo_lectivo_id') ?: null;
        $busqueda = trim((string) $request->string('busqueda'));

        $morosos = $calculador->tutoresEnMora($periodoLectivoId, $busqueda);

        $periodo = $periodoLectivoId ? PeriodoLectivo::find($periodoLectivoId) : null;

        $pdf = Pdf::loadView('mora.pdf', [
            'morosos' => $morosos,
            'periodo' => $periodo,
            'generadoEl' => now(),
        ]);

        return $pdf->download('morosos-'.now()->format('Y-m-d').'.pdf');
    }
}
