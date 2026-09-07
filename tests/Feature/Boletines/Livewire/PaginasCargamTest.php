<?php

use App\Boletines\Models\BoletinTrimestre;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('las paginas de boletines cargan con el layout de la app', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $trimestre = BoletinTrimestre::factory()->create();

    $this->actingAs($administrador)->get(route('boletines.index'))->assertOk();
    $this->actingAs($administrador)->get(route('boletines.trimestres.cargar', $trimestre))->assertOk();
});
