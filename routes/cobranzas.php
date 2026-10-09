<?php

use App\Cobranzas\Http\Controllers\ComprobantePagoController;
use App\Cobranzas\Http\Controllers\ImprimirComprobantePagoController;
use App\Cobranzas\Http\Controllers\PagosPdfController;
use App\Cobranzas\Http\Controllers\VerComprobantePagoController;
use App\Cobranzas\Livewire\Pagos\Listado as PagosListado;
use App\Cobranzas\Livewire\Pagos\Registrar as PagosRegistrar;
use Illuminate\Support\Facades\Route;

Route::get('pagos', PagosListado::class)->name('pagos.index');
Route::get('pagos/nuevo', PagosRegistrar::class)->name('pagos.registrar');
Route::get('pagos/pdf', PagosPdfController::class)->name('pagos.pdf');
Route::get('pagos/comprobantes/{numeroRecibo}', ComprobantePagoController::class)->name('pagos.comprobantes.descargar');
Route::get('pagos/comprobantes/{numeroRecibo}/ver', VerComprobantePagoController::class)->name('pagos.comprobantes.ver');
Route::get('pagos/comprobantes/{numeroRecibo}/imprimir', ImprimirComprobantePagoController::class)->name('pagos.comprobantes.imprimir');
