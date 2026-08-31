<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Livewire\Pagos\Listado;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Pago;
use App\Cobranzas\Models\PagoCuota;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('cobrador ve el listado pero no la accion de anular', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $pago = Pago::factory()->create();

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertSee($pago->numero_recibo)
        ->assertDontSee("prepararAnulacion({$pago->id})", false);
});

test('administrador puede anular un pago y la cuota afectada recalcula su estado', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $tutor = Tutor::factory()->create();
    $alumno = Alumno::factory()->primario()->create();
    $tutor->alumnos()->attach($alumno->id, ['vinculo' => 'Madre', 'responsable_pago' => true]);

    $cuota = Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'monto_base' => 100_000,
        'monto' => 100_000,
        'estado' => EstadoCuota::Pendiente,
    ]);

    $pago = Pago::factory()->create(['tutor_id' => $tutor->id, 'monto' => 100_000]);
    PagoCuota::create(['pago_id' => $pago->id, 'cuota_id' => $cuota->id, 'monto_aplicado' => 100_000]);

    expect($cuota->fresh()->estado)->toBe(EstadoCuota::Pagada);

    Livewire::actingAs($administrador)->test(Listado::class)
        ->assertSee('Anular')
        ->call('prepararAnulacion', $pago->id)
        ->set('motivoAnulacion', 'Error de carga')
        ->call('anular')
        ->assertHasNoErrors();

    expect($pago->fresh()->estaAnulado())->toBeTrue();
    expect($cuota->fresh()->estado)->toBe(EstadoCuota::Pendiente);
});

test('profesor no puede montar el componente', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Listado::class)->assertForbidden();
});
