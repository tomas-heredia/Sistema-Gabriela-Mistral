<?php

use App\Cobranzas\Models\Arancel;
use App\Cobranzas\Models\Enums\TipoCuota;
use App\Core\Livewire\PeriodosLectivos\Formulario;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('crear un periodo lo guarda inactivo, redirige al listado y crea sus 4 aranceles', function () {
    $administrador = User::factory()->create()->assignRole('administrador');

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('nombre', '2027')
        ->set('fecha_inicio', '2027-03-01')
        ->set('fecha_fin', '2027-12-15')
        ->set('descuento_hermanos_pct', '10')
        ->set('montos.primario_matricula', '1500')
        ->set('montos.primario_mensualidad', '1000')
        ->set('montos.secundario_matricula', '1800')
        ->set('montos.secundario_mensualidad', '1200')
        ->call('guardar')
        ->assertRedirect(route('periodos.index'));

    $periodo = PeriodoLectivo::where('nombre', '2027')->first();
    expect($periodo)->not->toBeNull()
        ->and($periodo->activo)->toBeFalse();

    expect(Arancel::where('periodo_lectivo_id', $periodo->id)->count())->toBe(4);

    $matriculaPrimario = Arancel::where('periodo_lectivo_id', $periodo->id)
        ->where('nivel', 'primario')->where('tipo', TipoCuota::Matricula)->first();
    expect($matriculaPrimario->monto)->toBe(150_000);
});

test('editar un periodo existente precarga sus datos y sus aranceles, y actualiza los montos', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $periodo = PeriodoLectivo::factory()->create(['nombre' => '2026']);
    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => 'primario']);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => 'primario']);
    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => 'secundario']);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => 'secundario']);

    Livewire::actingAs($administrador)->test(Formulario::class, ['periodoLectivo' => $periodo])
        ->assertSet('nombre', '2026')
        ->assertSet('montos.primario_matricula', '1500.00')
        ->assertSet('montos.primario_mensualidad', '1000.00')
        ->set('descuento_hermanos_pct', '20')
        ->set('montos.primario_mensualidad', '1100')
        ->call('guardar')
        ->assertRedirect(route('periodos.index'));

    expect($periodo->fresh()->descuento_hermanos_pct)->toBe('20.00');
    expect(Arancel::where('periodo_lectivo_id', $periodo->id)->count())->toBe(4);

    $mensualidadPrimario = Arancel::where('periodo_lectivo_id', $periodo->id)
        ->where('nivel', 'primario')->where('tipo', TipoCuota::Mensualidad)->first();
    expect($mensualidadPrimario->monto)->toBe(110_000);
});

test('los 4 aranceles son obligatorios', function () {
    $administrador = User::factory()->create()->assignRole('administrador');

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('nombre', '2027')
        ->set('fecha_inicio', '2027-03-01')
        ->set('fecha_fin', '2027-12-15')
        ->set('montos.primario_matricula', '1500')
        ->set('montos.primario_mensualidad', '1000')
        ->set('montos.secundario_matricula', '')
        ->set('montos.secundario_mensualidad', '1200')
        ->call('guardar')
        ->assertHasErrors(['montos.secundario_matricula' => 'required']);

    expect(PeriodoLectivo::where('nombre', '2027')->exists())->toBeFalse();
});

test('la fecha de fin no puede ser anterior a la de inicio', function () {
    $administrador = User::factory()->create()->assignRole('administrador');

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('nombre', '2027')
        ->set('fecha_inicio', '2027-03-01')
        ->set('fecha_fin', '2027-01-01')
        ->set('montos.primario_matricula', '1500')
        ->set('montos.primario_mensualidad', '1000')
        ->set('montos.secundario_matricula', '1800')
        ->set('montos.secundario_mensualidad', '1200')
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
        ->set('montos.primario_matricula', '1500')
        ->set('montos.primario_mensualidad', '1000')
        ->set('montos.secundario_matricula', '1800')
        ->set('montos.secundario_mensualidad', '1200')
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
