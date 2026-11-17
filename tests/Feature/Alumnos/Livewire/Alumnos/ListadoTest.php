<?php

use App\Alumnos\Livewire\Alumnos\Listado;
use App\Alumnos\Models\Alumno;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('un profesor no puede ver el listado de alumnos', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Listado::class)->assertForbidden();
});

test('eliminar desactiva al alumno en vez de borrarlo', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $alumno = Alumno::factory()->primario()->create(['activo' => true]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->call('eliminar', $alumno)
        ->assertOk();

    expect($alumno->fresh())
        ->activo->toBeFalse()
        ->and(Alumno::find($alumno->id))->not->toBeNull();
});

test('un alumno inactivo no aparece en el listado', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    Alumno::factory()->primario()->create(['nombre' => 'Activo Visible', 'activo' => true]);
    Alumno::factory()->primario()->create(['nombre' => 'Inactivo Oculto', 'activo' => false]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertSee('Activo Visible')
        ->assertDontSee('Inactivo Oculto');
});

test('el cobrador puede ver el listado y filtrar por nivel', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $primario = Alumno::factory()->primario()->create(['nombre' => 'Ana Primaria']);
    $secundario = Alumno::factory()->secundario()->create(['nombre' => 'Beto Secundario']);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertSee('Ana Primaria')
        ->assertSee('Beto Secundario')
        ->set('nivel', $primario->nivel->value)
        ->assertSee('Ana Primaria')
        ->assertDontSee('Beto Secundario');
});
