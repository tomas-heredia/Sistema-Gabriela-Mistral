<?php

use App\Cobranzas\Http\Controllers\PagosPdfController;
use App\Cobranzas\Livewire\Pagos\Listado as PagosListado;
use App\Cobranzas\Livewire\Pagos\Registrar as PagosRegistrar;
use Illuminate\Support\Facades\Route;

Route::get('pagos', PagosListado::class)->name('pagos.index');
Route::get('pagos/nuevo', PagosRegistrar::class)->name('pagos.registrar');
Route::get('pagos/pdf', PagosPdfController::class)->name('pagos.pdf');
