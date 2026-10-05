<?php

use App\Alumnos\Models\Alumno;
use App\Cobranzas\Exceptions\AsignacionDePagoInvalidaException;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Pago;
use App\Cobranzas\Services\AsignadorDePagos;

test('un pago exacto deja la cuota en pagada', function () {
    $cuota = Cuota::factory()->create(['monto' => 100_000, 'estado' => EstadoCuota::Pendiente]);
    $pago = Pago::factory()->create(['monto' => 100_000]);

    app(AsignadorDePagos::class)->aplicar($pago, [$cuota->id => ['monto' => 100_000, 'interes' => 0]]);

    expect($cuota->fresh()->estado)->toBe(EstadoCuota::Pagada);
});

test('un pago parcial deja la cuota en parcial con el saldo correcto', function () {
    $cuota = Cuota::factory()->create(['monto' => 100_000, 'estado' => EstadoCuota::Pendiente]);
    $pago = Pago::factory()->create(['monto' => 60_000]);

    app(AsignadorDePagos::class)->aplicar($pago, [$cuota->id => ['monto' => 60_000, 'interes' => 0]]);

    expect($cuota->fresh()->estado)->toBe(EstadoCuota::Parcial)
        ->and($cuota->fresh()->montoPagado())->toBe(60_000);
});

test('un pago puede cubrir dos cuotas (dos meses), una con su propio recibo', function () {
    $alumno = Alumno::factory()->create();
    $marzo = Cuota::factory()->create(['alumno_id' => $alumno->id, 'mes' => 3, 'monto' => 100_000]);
    $abril = Cuota::factory()->create(['alumno_id' => $alumno->id, 'mes' => 4, 'monto' => 100_000]);
    $pago = Pago::factory()->create(['monto' => 200_000]);

    app(AsignadorDePagos::class)->aplicar($pago, [
        $marzo->id => ['monto' => 100_000, 'interes' => 0],
        $abril->id => ['monto' => 100_000, 'interes' => 0],
    ]);

    $pagoCuotas = $pago->pagoCuotas()->get();

    expect($pagoCuotas)->toHaveCount(2)
        ->and($marzo->fresh()->estado)->toBe(EstadoCuota::Pagada)
        ->and($abril->fresh()->estado)->toBe(EstadoCuota::Pagada)
        ->and($pagoCuotas->pluck('numero_recibo')->unique())->toHaveCount(2);
});

test('el interes aplicado no cuenta para el saldo pendiente de la cuota', function () {
    $cuota = Cuota::factory()->create(['monto' => 100_000, 'estado' => EstadoCuota::Pendiente]);
    $pago = Pago::factory()->create(['monto' => 110_000]);

    app(AsignadorDePagos::class)->aplicar($pago, [$cuota->id => ['monto' => 100_000, 'interes' => 10_000]]);

    $pagoCuota = $pago->pagoCuotas()->first();

    expect($cuota->fresh()->estado)->toBe(EstadoCuota::Pagada)
        ->and($pagoCuota->interes_aplicado)->toBe(10_000)
        ->and($pagoCuota->montoTotal())->toBe(110_000);
});

test('rechaza si la suma de las asignaciones no coincide con el monto del pago', function () {
    $cuota = Cuota::factory()->create(['monto' => 100_000]);
    $pago = Pago::factory()->create(['monto' => 100_000]);

    app(AsignadorDePagos::class)->aplicar($pago, [$cuota->id => ['monto' => 90_000, 'interes' => 0]]);
})->throws(AsignacionDePagoInvalidaException::class);

test('rechaza si el monto aplicado supera el saldo pendiente de la cuota', function () {
    $cuota = Cuota::factory()->create(['monto' => 100_000]);
    $pago = Pago::factory()->create(['monto' => 150_000]);

    app(AsignadorDePagos::class)->aplicar($pago, [$cuota->id => ['monto' => 150_000, 'interes' => 0]]);
})->throws(AsignacionDePagoInvalidaException::class);

test('un pago adelantado se aplica contra una cuota futura ya existente', function () {
    $alumno = Alumno::factory()->create();
    $cuotaFutura = Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'mes' => 12,
        'monto' => 100_000,
        'fecha_vencimiento' => now()->addMonths(3),
    ]);
    $pago = Pago::factory()->create(['monto' => 100_000]);

    app(AsignadorDePagos::class)->aplicar($pago, [$cuotaFutura->id => ['monto' => 100_000, 'interes' => 0]]);

    expect($cuotaFutura->fresh()->estado)->toBe(EstadoCuota::Pagada);
});
