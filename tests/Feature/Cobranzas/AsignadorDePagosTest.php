<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Exceptions\AsignacionDePagoInvalidaException;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\MovimientoSaldoAFavor;
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

test('un pago puede cubrir dos cuotas (dos meses) del mismo alumno, con un solo recibo compartido', function () {
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
        ->and($pagoCuotas->pluck('numero_recibo')->unique())->toHaveCount(1);
});

test('un pago que cubre a dos alumnos distintos genera un recibo por cada uno', function () {
    $hermanoA = Alumno::factory()->create();
    $hermanoB = Alumno::factory()->create();
    $cuotaA = Cuota::factory()->create(['alumno_id' => $hermanoA->id, 'monto' => 100_000]);
    $cuotaB = Cuota::factory()->create(['alumno_id' => $hermanoB->id, 'monto' => 100_000]);
    $pago = Pago::factory()->create(['monto' => 200_000]);

    app(AsignadorDePagos::class)->aplicar($pago, [
        $cuotaA->id => ['monto' => 100_000, 'interes' => 0],
        $cuotaB->id => ['monto' => 100_000, 'interes' => 0],
    ]);

    $pagoCuotas = $pago->pagoCuotas()->get();

    expect($pagoCuotas->pluck('numero_recibo')->unique())->toHaveCount(2);
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

test('el excedente entregado de mas queda registrado como saldo a favor', function () {
    $tutor = Tutor::factory()->create();
    $cuota = Cuota::factory()->create(['monto' => 100_000, 'estado' => EstadoCuota::Pendiente]);
    $pago = Pago::factory()->create(['tutor_id' => $tutor->id, 'monto' => 130_000]);

    app(AsignadorDePagos::class)->aplicar(
        $pago,
        [$cuota->id => ['monto' => 100_000, 'interes' => 0]],
        saldoAFavorGenerado: 30_000,
    );

    expect((int) MovimientoSaldoAFavor::where('tutor_id', $tutor->id)->sum('monto'))->toBe(30_000);
});

test('se puede usar saldo a favor existente para completar una cuota pagando menos en efectivo', function () {
    $tutor = Tutor::factory()->create();
    $pagoAnterior = Pago::factory()->create(['tutor_id' => $tutor->id]);
    MovimientoSaldoAFavor::factory()->create(['tutor_id' => $tutor->id, 'pago_id' => $pagoAnterior->id, 'monto' => 30_000]);

    $cuota = Cuota::factory()->create(['monto' => 100_000, 'estado' => EstadoCuota::Pendiente]);
    $pago = Pago::factory()->create(['tutor_id' => $tutor->id, 'monto' => 70_000]);

    app(AsignadorDePagos::class)->aplicar(
        $pago,
        [$cuota->id => ['monto' => 100_000, 'interes' => 0]],
        saldoAFavorConsumido: 30_000,
    );

    expect($cuota->fresh()->estado)->toBe(EstadoCuota::Pagada)
        ->and(MovimientoSaldoAFavor::where('tutor_id', $tutor->id)->sum('monto'))->toBe(0);
});

test('rechaza usar mas saldo a favor del que el tutor tiene disponible', function () {
    $tutor = Tutor::factory()->create();
    $cuota = Cuota::factory()->create(['monto' => 100_000, 'estado' => EstadoCuota::Pendiente]);
    $pago = Pago::factory()->create(['tutor_id' => $tutor->id, 'monto' => 50_000]);

    app(AsignadorDePagos::class)->aplicar(
        $pago,
        [$cuota->id => ['monto' => 100_000, 'interes' => 0]],
        saldoAFavorConsumido: 50_000,
    );
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
