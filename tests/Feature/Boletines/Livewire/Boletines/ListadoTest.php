<?php

use App\Alumnos\Models\Alumno;
use App\Boletines\Livewire\Boletines\Listado;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('lista los boletines del periodo activo con el estado de sus trimestres', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->primario()->create(['nombre' => 'Ana Pérez']);
    $boletin = Boletin::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id]);
    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertSee('Ana Pérez')
        ->assertSee('T1');
});

test('no muestra boletines de otro periodo', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodoActivo = PeriodoLectivo::factory()->activo()->create();
    $periodoViejo = PeriodoLectivo::factory()->create(['activo' => false]);
    $alumno = Alumno::factory()->primario()->create(['nombre' => 'Bruno Gómez']);
    Boletin::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodoViejo->id]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertDontSee('Bruno Gómez');
});

test('busca por nombre o dni del alumno', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->primario()->create(['nombre' => 'Carla Díaz', 'dni' => '30111222']);
    Boletin::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->set('busqueda', '30111222')
        ->assertSee('Carla Díaz');
});

test('profesor no puede montar el componente', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Listado::class)->assertForbidden();
});
