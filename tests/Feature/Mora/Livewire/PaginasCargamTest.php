<?php

use App\Core\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('la pagina de mora carga con el layout de la app', function () {
    $administrador = User::factory()->create()->assignRole('administrador');

    $this->actingAs($administrador)->get(route('mora.index'))->assertOk();
});
