<?php

use App\Cobranzas\Livewire\Pagos\Listado as PagosListado;
use App\Cobranzas\Livewire\Pagos\Registrar as PagosRegistrar;
use Illuminate\Support\Facades\Route;

Route::get('pagos', PagosListado::class)->name('pagos.index');
Route::get('pagos/nuevo', PagosRegistrar::class)->name('pagos.registrar');
