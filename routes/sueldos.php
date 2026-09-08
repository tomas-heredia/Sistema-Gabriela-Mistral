<?php

use App\Sueldos\Http\Controllers\ReciboSueldoDescargaController;
use App\Sueldos\Livewire\RecibosSueldo\Formulario;
use App\Sueldos\Livewire\RecibosSueldo\Listado;
use Illuminate\Support\Facades\Route;

Route::get('recibos-sueldo', Listado::class)->name('recibos-sueldo.index');
Route::get('recibos-sueldo/nuevo', Formulario::class)->name('recibos-sueldo.crear');
Route::get('recibos-sueldo/{reciboSueldo}/descargar', ReciboSueldoDescargaController::class)->name('recibos-sueldo.descargar');
