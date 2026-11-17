<?php

use App\Cobranzas\Models\Arancel;
use App\Cobranzas\Models\Beca;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Pago;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

$modulo = [
    'Arancel' => Arancel::class,
    'Beca' => Beca::class,
    'Cuota' => Cuota::class,
    'Pago' => Pago::class,
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

test('solo administrador puede anularDeuda sobre Cuota', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $profesor = User::factory()->create()->assignRole('profesor');

    expect($administrador->can('anularDeuda', Cuota::class))->toBeTrue()
        ->and($cobrador->can('anularDeuda', Cuota::class))->toBeFalse()
        ->and($profesor->can('anularDeuda', Cuota::class))->toBeFalse();
});
