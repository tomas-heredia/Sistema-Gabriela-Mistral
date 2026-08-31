<?php

use App\Cobranzas\Exceptions\AsignacionDePagoInvalidaException;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Pago;
use App\Cobranzas\Services\AsignadorDePagos;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;

test('anular un pago recalcula el estado de las cuotas afectadas excluyendolo', function () {
    $cuota = Cuota::factory()->create(['monto' => 100_000, 'estado' => EstadoCuota::Pendiente]);
    $pagoMalCargado = Pago::factory()->create(['monto' => 100_000]);
    app(AsignadorDePagos::class)->aplicar($pagoMalCargado, [$cuota->id => 100_000]);

    expect($cuota->fresh()->estado)->toBe(EstadoCuota::Pagada);

    $administrador = User::factory()->create();
    $pagoMalCargado->anular('Cargado por error, monto incorrecto', $administrador);

    expect($pagoMalCargado->fresh()->estaAnulado())->toBeTrue()
        ->and($cuota->fresh()->estado)->toBe(EstadoCuota::Pendiente)
        ->and($cuota->fresh()->montoPagado())->toBe(0);
});

test('un pago anulado no se puede volver a aplicar', function () {
    $cuota = Cuota::factory()->create(['monto' => 100_000]);
    $pago = Pago::factory()->anulado()->create(['monto' => 100_000]);

    app(AsignadorDePagos::class)->aplicar($pago, [$cuota->id => 100_000]);
})->throws(AsignacionDePagoInvalidaException::class);

test('solo administrador puede anular un pago, cobrador no', function () {
    $this->seed(RoleSeeder::class);

    $pago = Pago::factory()->create();
    $administrador = User::factory()->create()->assignRole('administrador');
    $cobrador = User::factory()->create()->assignRole('cobrador');

    expect($administrador->can('anular', $pago))->toBeTrue()
        ->and($cobrador->can('anular', $pago))->toBeFalse();
});
