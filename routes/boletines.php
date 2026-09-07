<?php

use App\Boletines\Livewire\Boletines\Listado as BoletinesListado;
use App\Boletines\Livewire\Trimestres\Cargar as TrimestresCargar;
use Illuminate\Support\Facades\Route;

Route::get('boletines', BoletinesListado::class)->name('boletines.index');
Route::get('boletines/trimestres/{boletinTrimestre}', TrimestresCargar::class)->name('boletines.trimestres.cargar');
