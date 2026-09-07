<?php

use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('las paginas de periodos lectivos cargan con el layout de la app', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $periodo = PeriodoLectivo::factory()->create();

    $this->actingAs($administrador)->get(route('periodos.index'))->assertOk();
    $this->actingAs($administrador)->get(route('periodos.crear'))->assertOk();
    $this->actingAs($administrador)->get(route('periodos.editar', $periodo))->assertOk();
});
