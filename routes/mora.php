<?php

use App\Mora\Http\Controllers\MorososPdfController;
use App\Mora\Livewire\NotificacionesMora\Listado;
use Illuminate\Support\Facades\Route;

Route::get('mora', Listado::class)->name('mora.index');
Route::get('mora/pdf', MorososPdfController::class)->name('mora.pdf');
