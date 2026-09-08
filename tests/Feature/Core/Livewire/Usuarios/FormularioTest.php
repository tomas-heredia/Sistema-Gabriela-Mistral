<?php

use App\Core\Livewire\Usuarios\Formulario;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('crear un usuario lo guarda con el rol, la contraseña hasheada y el correo ya verificado', function () {
    $administrador = User::factory()->create()->assignRole('administrador');

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('name', 'Marta Gómez')
        ->set('email', 'marta@example.com')
        ->set('password', '123456')
        ->set('rol', 'cobrador')
        ->call('guardar')
        ->assertRedirect(route('usuarios.index'));

    $nuevo = User::where('email', 'marta@example.com')->firstOrFail();
    expect($nuevo->hasRole('cobrador'))->toBeTrue()
        ->and($nuevo->email_verified_at)->not->toBeNull()
        ->and(Hash::check('123456', $nuevo->password))->toBeTrue();
});

test('el correo debe ser unico', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    User::factory()->create(['email' => 'marta@example.com']);

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('name', 'Otra Marta')
        ->set('email', 'marta@example.com')
        ->set('password', '123456')
        ->set('rol', 'cobrador')
        ->call('guardar')
        ->assertHasErrors(['email' => 'unique']);
});

test('editar un usuario cambia nombre, correo y rol sin pedir contraseña', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $usuario = User::factory()->create(['name' => 'Marta Gómez'])->assignRole('cobrador');

    Livewire::actingAs($administrador)->test(Formulario::class, ['usuario' => $usuario])
        ->assertDontSee('Contraseña')
        ->set('name', 'Marta Gómez de López')
        ->set('rol', 'administrador')
        ->call('guardar')
        ->assertRedirect(route('usuarios.index'));

    $usuario->refresh();
    expect($usuario->name)->toBe('Marta Gómez de López')
        ->and($usuario->hasRole('administrador'))->toBeTrue()
        ->and($usuario->hasRole('cobrador'))->toBeFalse()
        ->and($usuario->roles)->toHaveCount(1);
});

test('cobrador y profesor no pueden montar el componente', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($cobrador)->test(Formulario::class)->assertForbidden();
    Livewire::actingAs($profesor)->test(Formulario::class)->assertForbidden();
});
