<?php

use App\Core\Livewire\Usuarios\Formulario;
use App\Core\Livewire\Usuarios\Listado;
use Illuminate\Support\Facades\Route;

Route::get('usuarios', Listado::class)->name('usuarios.index');
Route::get('usuarios/nuevo', Formulario::class)->name('usuarios.crear');
Route::get('usuarios/{usuario}/editar', Formulario::class)->name('usuarios.editar');
