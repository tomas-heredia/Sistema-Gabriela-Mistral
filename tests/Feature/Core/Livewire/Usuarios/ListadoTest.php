<?php

use App\Core\Livewire\Usuarios\Listado;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('lista los usuarios con su rol', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $cobrador = User::factory()->create(['name' => 'Marta Gómez'])->assignRole('cobrador');

    Livewire::actingAs($administrador)->test(Listado::class)
        ->assertSee('Marta Gómez')
        ->assertSee('Cobrador');
});

test('busca por nombre o correo', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    User::factory()->create(['name' => 'Carla Díaz', 'email' => 'carla@example.com'])->assignRole('profesor');
    User::factory()->create(['name' => 'Bruno Pérez'])->assignRole('cobrador');

    Livewire::actingAs($administrador)->test(Listado::class)
        ->set('busqueda', 'carla@example.com')
        ->assertSee('Carla Díaz')
        ->assertDontSee('Bruno Pérez');
});

test('un administrador no ve la opcion de eliminarse a si mismo', function () {
    $administrador = User::factory()->create()->assignRole('administrador');

    Livewire::actingAs($administrador)->test(Listado::class)
        ->assertDontSee("eliminar({$administrador->id})", false);
});

test('cobrador y profesor no pueden montar el componente', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($cobrador)->test(Listado::class)->assertForbidden();
    Livewire::actingAs($profesor)->test(Listado::class)->assertForbidden();
});
