<?php

use App\Boletines\Http\Controllers\BoletinPdfController;
use App\Boletines\Http\Controllers\ImprimirBoletinPdfController;
use App\Boletines\Http\Controllers\VerBoletinPdfController;
use App\Boletines\Livewire\Boletines\Listado as BoletinesListado;
use App\Boletines\Livewire\Trimestres\Cargar as TrimestresCargar;
use Illuminate\Support\Facades\Route;

Route::get('boletines', BoletinesListado::class)->name('boletines.index');
Route::get('boletines/trimestres/{boletinTrimestre}', TrimestresCargar::class)->name('boletines.trimestres.cargar');
Route::get('boletines/trimestres/{boletinTrimestre}/pdf', BoletinPdfController::class)->name('boletines.trimestres.pdf.descargar');
Route::get('boletines/trimestres/{boletinTrimestre}/pdf/ver', VerBoletinPdfController::class)->name('boletines.trimestres.pdf.ver');
Route::get('boletines/trimestres/{boletinTrimestre}/pdf/imprimir', ImprimirBoletinPdfController::class)->name('boletines.trimestres.pdf.imprimir');
