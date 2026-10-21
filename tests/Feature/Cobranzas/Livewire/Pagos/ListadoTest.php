<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Jobs\EnviarComprobantePago;
use App\Cobranzas\Livewire\Pagos\Listado;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Pago;
use App\Cobranzas\Models\PagoCuota;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('cobrador ve el listado y el link para reenviar el comprobante', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $pago = Pago::factory()->create();
    $pagoCuota = PagoCuota::factory()->create(['pago_id' => $pago->id]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertSee($pagoCuota->numero_recibo)
        ->assertSee('Reenviar');
});

/**
 * El botón de anular está oculto a pedido del cliente (no lo necesitan en su
 * operatoria), pero la capacidad en sí queda intacta para no perder la única
 * forma que tiene el sistema de corregir un pago mal cargado (CLAUDE.md:
 * un pago nunca se edita ni se borra, se anula) -- por eso el test llama a
 * los métodos del componente directo, sin pasar por un botón que ya no existe.
 */
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
    PagoCuota::create(['pago_id' => $pago->id, 'cuota_id' => $cuota->id, 'monto_aplicado' => 100_000, 'numero_recibo' => '000777']);

    expect($cuota->fresh()->estado)->toBe(EstadoCuota::Pagada);

    Livewire::actingAs($administrador)->test(Listado::class)
        ->call('prepararAnulacion', $pago->id)
        ->set('motivoAnulacion', 'Error de carga')
        ->call('anular')
        ->assertHasNoErrors();

    expect($pago->fresh()->estaAnulado())->toBeTrue();
    expect($cuota->fresh()->estado)->toBe(EstadoCuota::Pendiente);
});

test('la busqueda encuentra un pago por el nombre del tutor', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $tutor = Tutor::factory()->create(['nombre' => 'Marta Gómez']);
    $pago = Pago::factory()->create(['tutor_id' => $tutor->id]);
    $pagoCuota = PagoCuota::factory()->create(['pago_id' => $pago->id]);
    $otroPago = Pago::factory()->create();
    $otroPagoCuota = PagoCuota::factory()->create(['pago_id' => $otroPago->id]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->set('busqueda', 'Gómez')
        ->assertSee($pagoCuota->numero_recibo)
        ->assertDontSee($otroPagoCuota->numero_recibo);
});

test('la busqueda encuentra un pago por el nombre del alumno del tutor', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $tutor = Tutor::factory()->create();
    $alumno = Alumno::factory()->primario()->create(['nombre' => 'Lucas Peralta']);
    $tutor->alumnos()->attach($alumno->id, ['vinculo' => 'Madre', 'responsable_pago' => true]);
    $pago = Pago::factory()->create(['tutor_id' => $tutor->id]);
    $pagoCuota = PagoCuota::factory()->create(['pago_id' => $pago->id]);
    $otroPago = Pago::factory()->create();
    $otroPagoCuota = PagoCuota::factory()->create(['pago_id' => $otroPago->id]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->set('busqueda', 'Peralta')
        ->assertSee($pagoCuota->numero_recibo)
        ->assertDontSee($otroPagoCuota->numero_recibo);
});

test('sin pagos, el boton de generar pdf aparece deshabilitado', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertSeeHtml('No hay pagos en la tabla para generar el PDF.')
        ->assertDontSeeHtml(route('pagos.pdf'));
});

test('con pagos en la tabla, el boton de generar pdf esta habilitado', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    Pago::factory()->create();

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertSeeHtml(route('pagos.pdf'))
        ->assertDontSeeHtml('No hay pagos en la tabla para generar el PDF.');
});

test('cobrador puede reenviar el comprobante de un pago vigente', function () {
    Bus::fake();
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $pago = Pago::factory()->create();
    $pagoCuota = PagoCuota::factory()->create(['pago_id' => $pago->id]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->call('reenviarComprobante', $pagoCuota->numero_recibo)
        ->assertHasNoErrors();

    Bus::assertDispatched(EnviarComprobantePago::class, fn ($job) => $job->numeroRecibo === $pagoCuota->numero_recibo);
});

test('no se puede reenviar el comprobante de un pago anulado', function () {
    Bus::fake();
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $pago = Pago::factory()->create(['anulado_at' => now()]);
    $pagoCuota = PagoCuota::factory()->create(['pago_id' => $pago->id]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->call('reenviarComprobante', $pagoCuota->numero_recibo);

    Bus::assertNotDispatched(EnviarComprobantePago::class);
});

test('profesor no puede montar el componente', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Listado::class)->assertForbidden();
});
