<?php

namespace App\Sueldos\Http\Controllers;

use App\Core\Http\Controllers\Controller;
use App\Sueldos\Models\ReciboSueldo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReciboSueldoDescargaController extends Controller
{
    public function __invoke(ReciboSueldo $reciboSueldo): StreamedResponse
    {
        Gate::authorize('view', $reciboSueldo);

        return Storage::disk('local')->download($reciboSueldo->archivo, "recibo-{$reciboSueldo->periodo}.pdf");
    }
}
