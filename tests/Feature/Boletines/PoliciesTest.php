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

test('profesor con permiso cargar_boletines puede viewAny/view/update sobre Boletin pero no create ni delete', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    $profesor->givePermissionTo('cargar_boletines');
    $boletin = Boletin::factory()->create();

    expect($profesor->can('viewAny', Boletin::class))->toBeTrue()
        ->and($profesor->can('view', $boletin))->toBeTrue()
        ->and($profesor->can('update', $boletin))->toBeTrue()
        ->and($profesor->can('create', Boletin::class))->toBeFalse()
        ->and($profesor->can('delete', $boletin))->toBeFalse();
});

test('solo administrador y director_secretario pueden corregir un boletin ya enviado; cobrador y profesor con permiso no', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $directorSecretario = User::factory()->create()->assignRole('director_secretario');
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $profesor = User::factory()->create()->assignRole('profesor');
    $profesor->givePermissionTo('cargar_boletines');
    $boletin = Boletin::factory()->create();

    expect($administrador->can('corregir', $boletin))->toBeTrue()
        ->and($directorSecretario->can('corregir', $boletin))->toBeTrue()
        ->and($cobrador->can('corregir', $boletin))->toBeFalse()
        ->and($profesor->can('corregir', $boletin))->toBeFalse();
});

test('director_secretario puede ver y cargar boletines sin el permiso cargar_boletines, pero no crear', function () {
    $directorSecretario = User::factory()->create()->assignRole('director_secretario');
    $boletin = Boletin::factory()->create();

    expect($directorSecretario->can('viewAny', Boletin::class))->toBeTrue()
        ->and($directorSecretario->can('view', $boletin))->toBeTrue()
        ->and($directorSecretario->can('update', $boletin))->toBeTrue()
        ->and($directorSecretario->can('create', Boletin::class))->toBeFalse();
});

test('administrador, cobrador y director_secretario pueden eliminar un boletin; profesor con permiso no', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $directorSecretario = User::factory()->create()->assignRole('director_secretario');
    $profesor = User::factory()->create()->assignRole('profesor');
    $profesor->givePermissionTo('cargar_boletines');
    $boletin = Boletin::factory()->create();

    expect($administrador->can('delete', $boletin))->toBeTrue()
        ->and($cobrador->can('delete', $boletin))->toBeTrue()
        ->and($directorSecretario->can('delete', $boletin))->toBeTrue()
        ->and($profesor->can('delete', $boletin))->toBeFalse();
});

test('profesor sin permiso cargar_boletines no tiene acceso a Boletin', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    $boletin = Boletin::factory()->create();

    expect($profesor->can('viewAny', Boletin::class))->toBeFalse()
        ->and($profesor->can('view', $boletin))->toBeFalse()
        ->and($profesor->can('update', $boletin))->toBeFalse();
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
