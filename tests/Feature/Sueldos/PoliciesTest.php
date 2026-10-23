<?php

use App\Core\Models\User;
use App\Sueldos\Models\ReciboSueldo;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('administrador puede viewAny, create y ver cualquier recibo', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $recibo = ReciboSueldo::factory()->create();

    expect($administrador->can('viewAny', ReciboSueldo::class))->toBeTrue()
        ->and($administrador->can('create', ReciboSueldo::class))->toBeTrue()
        ->and($administrador->can('view', $recibo))->toBeTrue();
});

test('un profesor puede ver su propio recibo pero no el de otro profesor', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    $suRecibo = ReciboSueldo::factory()->create(['profesor_id' => $profesor->id]);
    $reciboDeOtro = ReciboSueldo::factory()->create();

    expect($profesor->can('viewAny', ReciboSueldo::class))->toBeTrue()
        ->and($profesor->can('view', $suRecibo))->toBeTrue()
        ->and($profesor->can('view', $reciboDeOtro))->toBeFalse()
        ->and($profesor->can('create', ReciboSueldo::class))->toBeFalse();
});

test('un cobrador puede ver su propio recibo pero no el de otro usuario, y no puede crear', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $suRecibo = ReciboSueldo::factory()->create(['profesor_id' => $cobrador->id]);
    $reciboDeOtro = ReciboSueldo::factory()->create();

    expect($cobrador->can('viewAny', ReciboSueldo::class))->toBeTrue()
        ->and($cobrador->can('view', $suRecibo))->toBeTrue()
        ->and($cobrador->can('view', $reciboDeOtro))->toBeFalse()
        ->and($cobrador->can('create', ReciboSueldo::class))->toBeFalse();
});
