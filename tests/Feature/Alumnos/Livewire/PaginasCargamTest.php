<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Livewire::test() no pasa por el envoltorio de layout de un
 * "full-page component" (solo ocurre en un request HTTP real) — por eso
 * estos tests, distintos de los que ya usan Livewire::test(), son los
 * que hubieran atrapado el bug real de "Livewire page component layout
 * view not found" antes de que apareciera en producción.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('las paginas de tutores y alumnos cargan con el layout de la app', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $tutor = Tutor::factory()->create();
    $alumno = Alumno::factory()->create();

    $this->actingAs($administrador)->get(route('tutores.index'))->assertOk();
    $this->actingAs($administrador)->get(route('tutores.crear'))->assertOk();
    $this->actingAs($administrador)->get(route('tutores.editar', $tutor))->assertOk();
    $this->actingAs($administrador)->get(route('alumnos.index'))->assertOk();
    $this->actingAs($administrador)->get(route('alumnos.crear'))->assertOk();
    $this->actingAs($administrador)->get(route('alumnos.editar', $alumno))->assertOk();
});
