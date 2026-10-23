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

test('un cobrador fuera de la red del colegio solo ve su propio recibo y no puede crear', function () {
    config(['acceso.restriccion_de_red_habilitada' => true]);
    request()->server->set('REMOTE_ADDR', '190.191.10.20');

    $cobrador = User::factory()->create()->assignRole('cobrador');
    $suRecibo = ReciboSueldo::factory()->create(['profesor_id' => $cobrador->id]);
    $reciboDeOtro = ReciboSueldo::factory()->create();

    expect($cobrador->can('viewAny', ReciboSueldo::class))->toBeTrue()
        ->and($cobrador->can('view', $suRecibo))->toBeTrue()
        ->and($cobrador->can('view', $reciboDeOtro))->toBeFalse()
        ->and($cobrador->can('create', ReciboSueldo::class))->toBeFalse();
});

test('un cobrador en la red del colegio puede ver y crear cualquier recibo, como administrador', function () {
    config(['acceso.restriccion_de_red_habilitada' => true]);
    request()->server->set('REMOTE_ADDR', '100.90.12.34');

    $cobrador = User::factory()->create()->assignRole('cobrador');
    $reciboDeOtro = ReciboSueldo::factory()->create();

    expect($cobrador->can('viewAny', ReciboSueldo::class))->toBeTrue()
        ->and($cobrador->can('view', $reciboDeOtro))->toBeTrue()
        ->and($cobrador->can('create', ReciboSueldo::class))->toBeTrue()
        // Eliminar nunca se le abrió a cobrador, ni siquiera en la red del colegio.
        ->and($cobrador->can('delete', $reciboDeOtro))->toBeFalse();
});

test('con la restriccion de red apagada, un cobrador puede gestionar recibos de sueldo igual que administrador', function () {
    config(['acceso.restriccion_de_red_habilitada' => false]);
    request()->server->set('REMOTE_ADDR', '190.191.10.20');

    $cobrador = User::factory()->create()->assignRole('cobrador');
    $reciboDeOtro = ReciboSueldo::factory()->create();

    expect($cobrador->can('view', $reciboDeOtro))->toBeTrue()
        ->and($cobrador->can('create', ReciboSueldo::class))->toBeTrue();
});
