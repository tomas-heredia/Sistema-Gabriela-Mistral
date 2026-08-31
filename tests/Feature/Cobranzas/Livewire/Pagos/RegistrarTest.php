<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Livewire\Pagos\Registrar;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\MedioPago;
use App\Cobranzas\Models\Pago;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->periodo = PeriodoLectivo::factory()->activo()->create();
    $this->tutor = Tutor::factory()->create();
});

function vincularAlumnoATutor(Tutor $tutor, Alumno $alumno): void
{
    $tutor->alumnos()->attach($alumno->id, ['vinculo' => 'Madre', 'responsable_pago' => true]);
}

test('buscar tutor lista las cuotas pendientes de sus alumnos en el periodo activo', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $alumno = Alumno::factory()->primario()->create();
    vincularAlumnoATutor($this->tutor, $alumno);

    $cuotaPendiente = Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_lectivo_id' => $this->periodo->id,
        'estado' => EstadoCuota::Pendiente,
    ]);

    // Cuota de otro período: no debe aparecer.
    Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_lectivo_id' => PeriodoLectivo::factory()->create()->id,
        'estado' => EstadoCuota::Pendiente,
    ]);

    Livewire::actingAs($cobrador)->test(Registrar::class)
        ->set('dniTutorBuscado', $this->tutor->dni)
        ->call('buscarTutor')
        ->assertSee($alumno->nombre)
        ->assertSee(number_format($cuotaPendiente->monto / 100, 2, ',', '.'));
});

test('pago exacto de una cuota la deja pagada', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $alumno = Alumno::factory()->primario()->create();
    vincularAlumnoATutor($this->tutor, $alumno);

    $cuota = Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_lectivo_id' => $this->periodo->id,
        'monto_base' => 100_000,
        'monto' => 100_000,
        'estado' => EstadoCuota::Pendiente,
    ]);

    Livewire::actingAs($cobrador)->test(Registrar::class)
        ->set('dniTutorBuscado', $this->tutor->dni)
        ->call('buscarTutor')
        ->set("cuotasSeleccionadas.{$cuota->id}", true)
        ->set("montos.{$cuota->id}", '1000.00')
        ->set('medio_pago', MedioPago::Efectivo->value)
        ->set('fecha', now()->format('Y-m-d'))
        ->set('numero_recibo', 'R-000001')
        ->call('guardar')
        ->assertHasNoErrors();

    expect($cuota->fresh()->estado)->toBe(EstadoCuota::Pagada);
    expect(Pago::where('numero_recibo', 'R-000001')->first()->monto)->toBe(100_000);
});

test('pago parcial deja la cuota en estado parcial con el saldo correcto', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $alumno = Alumno::factory()->primario()->create();
    vincularAlumnoATutor($this->tutor, $alumno);

    $cuota = Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_lectivo_id' => $this->periodo->id,
        'monto_base' => 100_000,
        'monto' => 100_000,
        'estado' => EstadoCuota::Pendiente,
    ]);

    Livewire::actingAs($cobrador)->test(Registrar::class)
        ->set('dniTutorBuscado', $this->tutor->dni)
        ->call('buscarTutor')
        ->set("cuotasSeleccionadas.{$cuota->id}", true)
        ->set("montos.{$cuota->id}", '400.00')
        ->set('medio_pago', MedioPago::Efectivo->value)
        ->set('fecha', now()->format('Y-m-d'))
        ->set('numero_recibo', 'R-000002')
        ->call('guardar')
        ->assertHasNoErrors();

    $cuota->refresh();
    expect($cuota->estado)->toBe(EstadoCuota::Parcial);
    expect($cuota->montoPagado())->toBe(40_000);
});

test('un pago que tilda dos cuotas de meses distintos aplica ambas', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $alumno = Alumno::factory()->primario()->create();
    vincularAlumnoATutor($this->tutor, $alumno);

    $cuotaMarzo = Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_lectivo_id' => $this->periodo->id,
        'mes' => 3,
        'monto_base' => 100_000,
        'monto' => 100_000,
        'estado' => EstadoCuota::Pendiente,
    ]);
    $cuotaAbril = Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_lectivo_id' => $this->periodo->id,
        'mes' => 4,
        'monto_base' => 100_000,
        'monto' => 100_000,
        'estado' => EstadoCuota::Pendiente,
    ]);

    $componente = Livewire::actingAs($cobrador)->test(Registrar::class)
        ->set('dniTutorBuscado', $this->tutor->dni)
        ->call('buscarTutor')
        ->set("cuotasSeleccionadas.{$cuotaMarzo->id}", true)
        ->set("cuotasSeleccionadas.{$cuotaAbril->id}", true)
        ->set("montos.{$cuotaMarzo->id}", '1000.00')
        ->set("montos.{$cuotaAbril->id}", '1000.00');

    expect($componente->get('montoTotal'))->toBe(200_000);

    $componente->set('medio_pago', MedioPago::Transferencia->value)
        ->set('fecha', now()->format('Y-m-d'))
        ->set('numero_recibo', 'R-000003')
        ->call('guardar')
        ->assertHasNoErrors();

    expect($cuotaMarzo->fresh()->estado)->toBe(EstadoCuota::Pagada);
    expect($cuotaAbril->fresh()->estado)->toBe(EstadoCuota::Pagada);
    expect(Pago::where('numero_recibo', 'R-000003')->first()->monto)->toBe(200_000);
});

test('profesor no puede montar el componente', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Registrar::class)->assertForbidden();
});
