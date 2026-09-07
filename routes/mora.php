<?php

use App\Mora\Livewire\NotificacionesMora\Listado;
use Illuminate\Support\Facades\Route;

Route::get('mora', Listado::class)->name('mora.index');
