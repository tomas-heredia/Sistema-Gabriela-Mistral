<?php

use App\Core\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('las paginas de recibos de sueldo cargan con el layout de la app', function () {
    $administrador = User::factory()->create()->assignRole('administrador');

    $this->actingAs($administrador)->get(route('recibos-sueldo.index'))->assertOk();
    $this->actingAs($administrador)->get(route('recibos-sueldo.crear'))->assertOk();
});
