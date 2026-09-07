<?php

use App\Core\Livewire\PeriodosLectivos\Listado;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('lista los periodos con su estado', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    PeriodoLectivo::factory()->activo()->create(['nombre' => '2026']);
    PeriodoLectivo::factory()->create(['nombre' => '2027']);

    Livewire::actingAs($administrador)->test(Listado::class)
        ->assertSee('2026')
        ->assertSee('2027')
        ->assertSee('Activo')
        ->assertSee('Inactivo');
});

test('activar desactiva el periodo anterior y activa el nuevo', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $viejo = PeriodoLectivo::factory()->activo()->create();
    $nuevo = PeriodoLectivo::factory()->create();

    Livewire::actingAs($administrador)->test(Listado::class)
        ->call('activar', $nuevo->id);

    expect($viejo->fresh()->activo)->toBeFalse()
        ->and($nuevo->fresh()->activo)->toBeTrue();
});

test('cobrador ve el listado pero no puede activar ni editar', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->create();

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertSee($periodo->nombre)
        ->assertDontSee("activar({$periodo->id})", false)
        ->assertDontSee(route('periodos.editar', $periodo), false);
});

test('profesor no puede montar el componente', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Listado::class)->assertForbidden();
});
