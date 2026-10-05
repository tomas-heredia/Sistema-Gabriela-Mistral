<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Livewire\Pagos\Registrar;
use App\Cobranzas\Mail\ComprobantePagoEnviado;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\MedioPago;
use App\Cobranzas\Models\Pago;
use App\Cobranzas\Models\PagoCuota;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
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
        ->set('busquedaTutor', $this->tutor->dni)
        ->call('buscarTutor')
        ->assertSee($alumno->nombre)
        ->assertSee(number_format($cuotaPendiente->monto / 100, 2, ',', '.'));
});

test('buscar tutor por su nombre lo selecciona solo si es la unica coincidencia', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $this->tutor->update(['nombre' => 'Marta Gómez']);
    $alumno = Alumno::factory()->primario()->create();
    vincularAlumnoATutor($this->tutor, $alumno);

    Livewire::actingAs($cobrador)->test(Registrar::class)
        ->set('busquedaTutor', 'Gómez')
        ->call('buscarTutor')
        ->assertSet('tutorEncontrado.id', $this->tutor->id)
        ->assertSee('Marta Gómez');
});

test('buscar tutor por el nombre de su alumno lo encuentra', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $alumno = Alumno::factory()->primario()->create(['nombre' => 'Lucas Peralta']);
    vincularAlumnoATutor($this->tutor, $alumno);

    Livewire::actingAs($cobrador)->test(Registrar::class)
        ->set('busquedaTutor', 'Peralta')
        ->call('buscarTutor')
        ->assertSet('tutorEncontrado.id', $this->tutor->id);
});

test('buscar un nombre con varios tutores coincidentes muestra la lista para elegir', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $this->tutor->update(['nombre' => 'Marta Gómez']);
    $otroTutor = Tutor::factory()->create(['nombre' => 'Pedro Gómez']);
    $alumno = Alumno::factory()->primario()->create();
    vincularAlumnoATutor($this->tutor, $alumno);

    $componente = Livewire::actingAs($cobrador)->test(Registrar::class)
        ->set('busquedaTutor', 'Gómez')
        ->call('buscarTutor')
        ->assertSet('tutorEncontrado', null)
        ->assertSee('Marta Gómez')
        ->assertSee('Pedro Gómez');

    expect($componente->get('resultadosBusqueda'))->toHaveCount(2);

    $componente->call('seleccionarTutor', $otroTutor->id)
        ->assertSet('tutorEncontrado.id', $otroTutor->id);

    expect($componente->get('resultadosBusqueda'))->toBe([]);
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
        ->set('busquedaTutor', $this->tutor->dni)
        ->call('buscarTutor')
        ->set("cuotasSeleccionadas.{$cuota->id}", true)
        ->set("montos.{$cuota->id}", '1000.00')
        ->set('medio_pago', MedioPago::Efectivo->value)
        ->set('fecha', now()->format('Y-m-d'))
        ->call('guardar')
        ->assertHasNoErrors();

    expect($cuota->fresh()->estado)->toBe(EstadoCuota::Pagada);
    expect(Pago::where('tutor_id', $this->tutor->id)->first()->monto)->toBe(100_000);
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
        ->set('busquedaTutor', $this->tutor->dni)
        ->call('buscarTutor')
        ->set("cuotasSeleccionadas.{$cuota->id}", true)
        ->set("montos.{$cuota->id}", '400.00')
        ->set('medio_pago', MedioPago::Efectivo->value)
        ->set('fecha', now()->format('Y-m-d'))
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
        ->set('busquedaTutor', $this->tutor->dni)
        ->call('buscarTutor')
        ->set("cuotasSeleccionadas.{$cuotaMarzo->id}", true)
        ->set("cuotasSeleccionadas.{$cuotaAbril->id}", true)
        ->set("montos.{$cuotaMarzo->id}", '1000.00')
        ->set("montos.{$cuotaAbril->id}", '1000.00');

    expect($componente->get('montoTotal'))->toBe(200_000);

    $componente->set('medio_pago', MedioPago::Transferencia->value)
        ->set('fecha', now()->format('Y-m-d'))
        ->call('guardar')
        ->assertHasNoErrors();

    expect($cuotaMarzo->fresh()->estado)->toBe(EstadoCuota::Pagada);
    expect($cuotaAbril->fresh()->estado)->toBe(EstadoCuota::Pagada);
    expect(Pago::where('tutor_id', $this->tutor->id)->first()->monto)->toBe(200_000);
});

test('profesor no puede montar el componente', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Registrar::class)->assertForbidden();
});

test('el numero de recibo lo asigna el sistema, correlativo y con ceros a la izquierda', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $alumno = Alumno::factory()->primario()->create();
    vincularAlumnoATutor($this->tutor, $alumno);

    $cuota1 = Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_lectivo_id' => $this->periodo->id,
        'mes' => 3,
        'monto_base' => 100_000,
        'monto' => 100_000,
        'estado' => EstadoCuota::Pendiente,
    ]);
    $cuota2 = Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_lectivo_id' => $this->periodo->id,
        'mes' => 4,
        'monto_base' => 100_000,
        'monto' => 100_000,
        'estado' => EstadoCuota::Pendiente,
    ]);

    Livewire::actingAs($cobrador)->test(Registrar::class)
        ->set('busquedaTutor', $this->tutor->dni)
        ->call('buscarTutor')
        ->set("cuotasSeleccionadas.{$cuota1->id}", true)
        ->set("montos.{$cuota1->id}", '1000.00')
        ->set('medio_pago', MedioPago::Efectivo->value)
        ->set('fecha', now()->format('Y-m-d'))
        ->call('guardar')
        ->assertHasNoErrors();

    Livewire::actingAs($cobrador)->test(Registrar::class)
        ->set('busquedaTutor', $this->tutor->dni)
        ->call('buscarTutor')
        ->set("cuotasSeleccionadas.{$cuota2->id}", true)
        ->set("montos.{$cuota2->id}", '1000.00')
        ->set('medio_pago', MedioPago::Efectivo->value)
        ->set('fecha', now()->format('Y-m-d'))
        ->call('guardar')
        ->assertHasNoErrors();

    $numeros = PagoCuota::whereHas('pago', fn ($q) => $q->where('tutor_id', $this->tutor->id))
        ->orderBy('id')
        ->pluck('numero_recibo');

    expect($numeros)->toHaveCount(2)
        ->and($numeros[1])->toBe(str_pad((string) ((int) $numeros[0] + 1), 6, '0', STR_PAD_LEFT))
        ->and(strlen($numeros[0]))->toBe(6);
});

test('el interes se aplica a cada cuota por separado, no sobre el total combinado', function () {
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
        'monto_base' => 50_000,
        'monto' => 50_000,
        'estado' => EstadoCuota::Pendiente,
    ]);

    Livewire::actingAs($cobrador)->test(Registrar::class)
        ->set('busquedaTutor', $this->tutor->dni)
        ->call('buscarTutor')
        ->set("cuotasSeleccionadas.{$cuotaMarzo->id}", true)
        ->set("cuotasSeleccionadas.{$cuotaAbril->id}", true)
        ->set("montos.{$cuotaMarzo->id}", '1000.00')
        ->set("montos.{$cuotaAbril->id}", '500.00')
        ->set('medio_pago', MedioPago::Transferencia->value)
        ->set('interesPorcentaje', '10')
        ->set('fecha', now()->format('Y-m-d'))
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertCount('comprobantesGenerados', 2);

    $pago = Pago::where('tutor_id', $this->tutor->id)->first();
    $pagoCuotaMarzo = $pago->pagoCuotas()->where('cuota_id', $cuotaMarzo->id)->first();
    $pagoCuotaAbril = $pago->pagoCuotas()->where('cuota_id', $cuotaAbril->id)->first();

    // 10% sobre cada cuota por separado (10.000 y 5.000), no sobre el
    // combinado (150.000) -- si se aplicara sobre el total, cualquiera de
    // los dos números saldría distinto.
    expect($pagoCuotaMarzo->interes_aplicado)->toBe(10_000)
        ->and($pagoCuotaAbril->interes_aplicado)->toBe(5_000)
        ->and($pago->monto)->toBe(100_000 + 10_000 + 50_000 + 5_000)
        ->and((float) $pago->interes_porcentaje)->toBe(10.0);

    // El interés no es parte de la deuda de la cuota: paga el saldo
    // completo igual, sin que el recargo la deje "sobrepagada".
    expect($cuotaMarzo->fresh()->estado)->toBe(EstadoCuota::Pagada);
});

test('genera un comprobante descargable por cada mes pagado y lo envia por mail', function () {
    Mail::fake();

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
        ->set('busquedaTutor', $this->tutor->dni)
        ->call('buscarTutor')
        ->set("cuotasSeleccionadas.{$cuotaMarzo->id}", true)
        ->set("cuotasSeleccionadas.{$cuotaAbril->id}", true)
        ->set("montos.{$cuotaMarzo->id}", '1000.00')
        ->set("montos.{$cuotaAbril->id}", '1000.00')
        ->set('medio_pago', MedioPago::Efectivo->value)
        ->set('fecha', now()->format('Y-m-d'))
        ->call('guardar')
        ->assertHasNoErrors();

    $comprobantes = $componente->get('comprobantesGenerados');
    expect($comprobantes)->toHaveCount(2);

    $pagoCuotas = PagoCuota::whereHas('pago', fn ($q) => $q->where('tutor_id', $this->tutor->id))->get();
    expect($pagoCuotas)->toHaveCount(2);

    foreach ($pagoCuotas as $pagoCuota) {
        expect($pagoCuota->pdf_path)->not->toBeNull();
        Storage::disk('local')->assertExists($pagoCuota->pdf_path);
    }

    Mail::assertSent(ComprobantePagoEnviado::class, 2);
});
