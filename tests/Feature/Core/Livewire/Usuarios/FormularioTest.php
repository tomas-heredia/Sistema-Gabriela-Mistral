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

test('profesor no puede montar el componente, pero cobrador si para crear', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($cobrador)->test(Formulario::class)->assertOk();
    Livewire::actingAs($profesor)->test(Formulario::class)->assertForbidden();
});

test('cobrador solo puede elegir el rol profesor, y crear con ese rol', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $componente = Livewire::actingAs($cobrador)->test(Formulario::class)
        ->assertDontSee('Administrador')
        ->assertDontSee('Cobrador')
        ->assertSee('Profesor')
        ->set('name', 'Nuevo Profesor')
        ->set('email', 'nuevo.profesor@example.com')
        ->set('password', '123456')
        ->set('rol', 'profesor')
        ->call('guardar')
        ->assertHasNoErrors();

    $componente->assertRedirect(route('dashboard'));

    $nuevo = User::where('email', 'nuevo.profesor@example.com')->firstOrFail();
    expect($nuevo->hasRole('profesor'))->toBeTrue();
});

test('cobrador no puede crear un usuario con rol administrador o cobrador aunque lo mande a mano', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    Livewire::actingAs($cobrador)->test(Formulario::class)
        ->set('name', 'Intento Admin')
        ->set('email', 'intento@example.com')
        ->set('password', '123456')
        ->set('rol', 'administrador')
        ->call('guardar')
        ->assertHasErrors(['rol']);

    expect(User::where('email', 'intento@example.com')->exists())->toBeFalse();
});

test('se puede crear un profesor con permiso para cargar libretas', function () {
    $administrador = User::factory()->create()->assignRole('administrador');

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('name', 'Elena Ruiz')
        ->set('email', 'elena@example.com')
        ->set('password', '123456')
        ->set('rol', 'profesor')
        ->set('puedeCargarBoletines', true)
        ->call('guardar')
        ->assertRedirect(route('usuarios.index'));

    $nuevo = User::where('email', 'elena@example.com')->firstOrFail();
    expect($nuevo->hasRole('profesor'))->toBeTrue()
        ->and($nuevo->hasPermissionTo('cargar_boletines'))->toBeTrue();
});

test('un profesor sin tildar el checkbox no recibe el permiso de cargar libretas', function () {
    $administrador = User::factory()->create()->assignRole('administrador');

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('name', 'Elena Ruiz')
        ->set('email', 'elena@example.com')
        ->set('password', '123456')
        ->set('rol', 'profesor')
        ->call('guardar')
        ->assertRedirect(route('usuarios.index'));

    $nuevo = User::where('email', 'elena@example.com')->firstOrFail();
    expect($nuevo->hasPermissionTo('cargar_boletines'))->toBeFalse();
});

test('se puede reactivar a un usuario desactivado editandolo', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $usuario = User::factory()->inactivo()->create()->assignRole('cobrador');

    Livewire::actingAs($administrador)->test(Formulario::class, ['usuario' => $usuario])
        ->assertSet('activo', false)
        ->set('activo', true)
        ->call('guardar')
        ->assertRedirect(route('usuarios.index'));

    expect($usuario->fresh()->activo)->toBeTrue();
});

test('al cambiar a un usuario de profesor con permiso de libretas a otro rol, se le quita el permiso', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $usuario = User::factory()->create()->assignRole('profesor');
    $usuario->givePermissionTo('cargar_boletines');

    Livewire::actingAs($administrador)->test(Formulario::class, ['usuario' => $usuario])
        ->set('rol', 'cobrador')
        ->call('guardar')
        ->assertRedirect(route('usuarios.index'));

    expect($usuario->refresh()->hasPermissionTo('cargar_boletines'))->toBeFalse();
});
