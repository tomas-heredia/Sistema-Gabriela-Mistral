<?php

use App\Alumnos\Livewire\Alumnos\Formulario as AlumnoFormulario;
use App\Alumnos\Livewire\Alumnos\Listado as AlumnosListado;
use App\Alumnos\Livewire\Tutores\Formulario as TutorFormulario;
use App\Alumnos\Livewire\Tutores\Listado as TutoresListado;
use Illuminate\Support\Facades\Route;

Route::get('tutores', TutoresListado::class)->name('tutores.index');
Route::get('tutores/nuevo', TutorFormulario::class)->name('tutores.crear');
Route::get('tutores/{tutor}/editar', TutorFormulario::class)->name('tutores.editar');

Route::get('alumnos', AlumnosListado::class)->name('alumnos.index');
Route::get('alumnos/nuevo', AlumnoFormulario::class)->name('alumnos.crear');
Route::get('alumnos/{alumno}/editar', AlumnoFormulario::class)->name('alumnos.editar');
