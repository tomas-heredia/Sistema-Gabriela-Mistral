<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified'])->group(function () {
    require __DIR__.'/alumnos.php';
    require __DIR__.'/cobranzas.php';
    require __DIR__.'/boletines.php';
    require __DIR__.'/periodos.php';
    require __DIR__.'/mora.php';
    require __DIR__.'/sueldos.php';
    require __DIR__.'/usuarios.php';
});

require __DIR__.'/auth.php';
