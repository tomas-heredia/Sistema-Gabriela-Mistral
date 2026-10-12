<?php

namespace App\Sueldos\Http\Controllers;

use App\Core\Http\Controllers\Controller;
use App\Sueldos\Models\ReciboSueldoArchivo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReciboSueldoDescargaController extends Controller
{
    public function __invoke(ReciboSueldoArchivo $archivo): StreamedResponse
    {
        Gate::authorize('view', $archivo->reciboSueldo);

        return Storage::disk('local')->download($archivo->archivo, "recibo-{$archivo->reciboSueldo->periodo}.pdf");
    }
}
