<?php

use App\Alumnos\Livewire\Tutores\Listado;
use App\Alumnos\Models\Tutor;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('un profesor no puede ver el listado de tutores', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Listado::class)->assertForbidden();
});

test('el cobrador puede ver el listado y el buscador filtra por nombre', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    Tutor::factory()->create(['nombre' => 'Marta Gómez']);
    Tutor::factory()->create(['nombre' => 'Pedro López']);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertSee('Marta Gómez')
        ->assertSee('Pedro López')
        ->set('busqueda', 'Marta')
        ->assertSee('Marta Gómez')
        ->assertDontSee('Pedro López');
});

test('eliminar un tutor lo saca del listado', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $tutor = Tutor::factory()->create();

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->call('eliminar', $tutor->id);

    expect(Tutor::find($tutor->id))->toBeNull();
});
