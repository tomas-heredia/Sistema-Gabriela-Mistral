<?php

use App\Core\Livewire\PeriodosLectivos\Formulario as PeriodosLectivosFormulario;
use App\Core\Livewire\PeriodosLectivos\Listado as PeriodosLectivosListado;
use Illuminate\Support\Facades\Route;

Route::get('periodos-lectivos', PeriodosLectivosListado::class)->name('periodos.index');
Route::get('periodos-lectivos/nuevo', PeriodosLectivosFormulario::class)->name('periodos.crear');
Route::get('periodos-lectivos/{periodoLectivo}/editar', PeriodosLectivosFormulario::class)->name('periodos.editar');
