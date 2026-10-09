<?php

use App\Core\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('administrador puede viewAny, create, update y eliminar a otro usuario', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $otro = User::factory()->create()->assignRole('cobrador');

    expect($administrador->can('viewAny', User::class))->toBeTrue()
        ->and($administrador->can('create', User::class))->toBeTrue()
        ->and($administrador->can('update', $otro))->toBeTrue()
        ->and($administrador->can('delete', $otro))->toBeTrue();
});

test('un administrador no puede eliminarse a si mismo', function () {
    $administrador = User::factory()->create()->assignRole('administrador');

    expect($administrador->can('delete', $administrador))->toBeFalse();
});

test('cobrador puede crear usuarios pero no ver, editar ni eliminar la gestion de usuarios', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $otro = User::factory()->create()->assignRole('cobrador');

    expect($cobrador->can('viewAny', User::class))->toBeFalse()
        ->and($cobrador->can('create', User::class))->toBeTrue()
        ->and($cobrador->can('update', $otro))->toBeFalse()
        ->and($cobrador->can('delete', $otro))->toBeFalse();
});

test('profesor no tiene ningun acceso a la gestion de usuarios', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    $otro = User::factory()->create()->assignRole('cobrador');

    expect($profesor->can('viewAny', User::class))->toBeFalse()
        ->and($profesor->can('create', User::class))->toBeFalse()
        ->and($profesor->can('update', $otro))->toBeFalse();
});
