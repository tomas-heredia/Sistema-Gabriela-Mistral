<?php

use App\Boletines\Models\Boletin;
use App\Boletines\Models\PlantillaBoletin;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('administrador y cobrador pueden viewAny y create sobre Boletin, profesor no', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $profesor = User::factory()->create()->assignRole('profesor');

    expect($administrador->can('viewAny', Boletin::class))->toBeTrue()
        ->and($cobrador->can('viewAny', Boletin::class))->toBeTrue()
        ->and($cobrador->can('create', Boletin::class))->toBeTrue()
        ->and($profesor->can('viewAny', Boletin::class))->toBeFalse();
});

test('solo administrador puede crear/editar/borrar una plantilla de boletin; cobrador solo la lee', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $profesor = User::factory()->create()->assignRole('profesor');

    expect($administrador->can('create', PlantillaBoletin::class))->toBeTrue()
        ->and($cobrador->can('viewAny', PlantillaBoletin::class))->toBeTrue()
        ->and($cobrador->can('create', PlantillaBoletin::class))->toBeFalse()
        ->and($profesor->can('viewAny', PlantillaBoletin::class))->toBeFalse();
});
