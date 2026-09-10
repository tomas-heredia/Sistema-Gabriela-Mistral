<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

// PeriodoLectivo queda afuera de este loop genérico: a diferencia de
// Tutor/Alumno, su `create` está reservado a administrador
// (ver el test dedicado más abajo).
$modulo = [
    'Tutor' => Tutor::class,
    'Alumno' => Alumno::class,
];

foreach ($modulo as $nombre => $clase) {
    test("administrador puede viewAny y create sobre {$nombre}", function () use ($clase) {
        $user = User::factory()->create()->assignRole('administrador');

        expect($user->can('viewAny', $clase))->toBeTrue()
            ->and($user->can('create', $clase))->toBeTrue();
    });

    test("cobrador puede viewAny y create sobre {$nombre}", function () use ($clase) {
        $user = User::factory()->create()->assignRole('cobrador');

        expect($user->can('viewAny', $clase))->toBeTrue()
            ->and($user->can('create', $clase))->toBeTrue();
    });

    test("profesor no puede viewAny ni create sobre {$nombre}", function () use ($clase) {
        $user = User::factory()->create()->assignRole('profesor');

        expect($user->can('viewAny', $clase))->toBeFalse()
            ->and($user->can('create', $clase))->toBeFalse();
    });
}

test('profesor no puede viewAny ni create sobre PeriodoLectivo', function () {
    $user = User::factory()->create()->assignRole('profesor');

    expect($user->can('viewAny', PeriodoLectivo::class))->toBeFalse()
        ->and($user->can('create', PeriodoLectivo::class))->toBeFalse();
});

test('solo administrador puede crear/editar/borrar un periodo lectivo, cobrador solo lo lee', function () {
    $periodo = PeriodoLectivo::factory()->create();
    $administrador = User::factory()->create()->assignRole('administrador');
    $cobrador = User::factory()->create()->assignRole('cobrador');

    expect($administrador->can('create', PeriodoLectivo::class))->toBeTrue()
        ->and($administrador->can('update', $periodo))->toBeTrue()
        ->and($administrador->can('delete', $periodo))->toBeTrue()
        ->and($cobrador->can('viewAny', PeriodoLectivo::class))->toBeTrue()
        ->and($cobrador->can('view', $periodo))->toBeTrue()
        ->and($cobrador->can('create', PeriodoLectivo::class))->toBeFalse()
        ->and($cobrador->can('update', $periodo))->toBeFalse()
        ->and($cobrador->can('delete', $periodo))->toBeFalse();
});
