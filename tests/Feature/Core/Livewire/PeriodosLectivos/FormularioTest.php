<?php

use App\Core\Livewire\PeriodosLectivos\Formulario;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('crear un periodo lo guarda inactivo y redirige al listado', function () {
    $administrador = User::factory()->create()->assignRole('administrador');

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('nombre', '2027')
        ->set('fecha_inicio', '2027-03-01')
        ->set('fecha_fin', '2027-12-15')
        ->set('descuento_hermanos_pct', '10')
        ->call('guardar')
        ->assertRedirect(route('periodos.index'));

    $periodo = PeriodoLectivo::where('nombre', '2027')->first();
    expect($periodo)->not->toBeNull()
        ->and($periodo->activo)->toBeFalse();
});

test('editar un periodo existente precarga sus datos', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $periodo = PeriodoLectivo::factory()->create(['nombre' => '2026']);

    Livewire::actingAs($administrador)->test(Formulario::class, ['periodoLectivo' => $periodo])
        ->assertSet('nombre', '2026')
        ->set('descuento_hermanos_pct', '20')
        ->call('guardar')
        ->assertRedirect(route('periodos.index'));

    expect($periodo->fresh()->descuento_hermanos_pct)->toBe('20.00');
});

test('la fecha de fin no puede ser anterior a la de inicio', function () {
    $administrador = User::factory()->create()->assignRole('administrador');

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('nombre', '2027')
        ->set('fecha_inicio', '2027-03-01')
        ->set('fecha_fin', '2027-01-01')
        ->call('guardar')
        ->assertHasErrors(['fecha_fin' => 'after']);
});

test('el descuento por hermanos tiene que estar entre 0 y 100', function () {
    $administrador = User::factory()->create()->assignRole('administrador');

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('nombre', '2027')
        ->set('fecha_inicio', '2027-03-01')
        ->set('fecha_fin', '2027-12-15')
        ->set('descuento_hermanos_pct', '150')
        ->call('guardar')
        ->assertHasErrors(['descuento_hermanos_pct' => 'max']);
});

test('un cobrador no puede montar el formulario', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    Livewire::actingAs($cobrador)->test(Formulario::class)->assertForbidden();
});

test('un profesor no puede montar el formulario', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Formulario::class)->assertForbidden();
});
