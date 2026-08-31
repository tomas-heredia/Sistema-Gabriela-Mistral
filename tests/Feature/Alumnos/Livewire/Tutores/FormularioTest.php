<?php

use App\Alumnos\Livewire\Tutores\Formulario;
use App\Alumnos\Models\Tutor;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('un profesor no puede montar el formulario de tutor', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Formulario::class)->assertForbidden();
});

test('crear un tutor guarda y redirige al listado', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    Livewire::actingAs($cobrador)->test(Formulario::class)
        ->set('nombre', 'Marta Gómez')
        ->set('dni', '30111222')
        ->set('domicilio', 'San Martín 123')
        ->set('telefono', '3834555555')
        ->set('correo', 'marta@example.com')
        ->call('guardar')
        ->assertRedirect(route('tutores.index'));

    expect(Tutor::where('dni', '30111222')->exists())->toBeTrue();
});

test('el formulario rechaza un dni duplicado', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    Tutor::factory()->create(['dni' => '30111222']);

    Livewire::actingAs($cobrador)->test(Formulario::class)
        ->set('nombre', 'Otro Nombre')
        ->set('dni', '30111222')
        ->set('domicilio', 'Calle 1')
        ->set('telefono', '123')
        ->call('guardar')
        ->assertHasErrors(['dni']);
});

test('editar un tutor precarga sus datos', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $tutor = Tutor::factory()->create(['nombre' => 'Marta Gómez']);

    Livewire::actingAs($cobrador)->test(Formulario::class, ['tutor' => $tutor])
        ->assertSet('nombre', 'Marta Gómez')
        ->set('nombre', 'Marta Gómez de López')
        ->call('guardar')
        ->assertRedirect(route('tutores.index'));

    expect($tutor->fresh()->nombre)->toBe('Marta Gómez de López');
});
