<?php

use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\MovimientoSaldoAFavor;
use App\Cobranzas\Models\Pago;
use App\Cobranzas\Services\CalculadorDeSaldoAFavor;

test('suma los movimientos de un tutor', function () {
    $tutor = Tutor::factory()->create();

    MovimientoSaldoAFavor::factory()->create(['tutor_id' => $tutor->id, 'monto' => 5_000]);
    MovimientoSaldoAFavor::factory()->create(['tutor_id' => $tutor->id, 'monto' => -2_000]);

    expect(app(CalculadorDeSaldoAFavor::class)->calcular($tutor))->toBe(3_000);
});

test('no cuenta movimientos de un pago anulado', function () {
    $tutor = Tutor::factory()->create();
    $pagoAnulado = Pago::factory()->create(['tutor_id' => $tutor->id, 'anulado_at' => now()]);

    MovimientoSaldoAFavor::factory()->create(['tutor_id' => $tutor->id, 'pago_id' => $pagoAnulado->id, 'monto' => 5_000]);
    MovimientoSaldoAFavor::factory()->create(['tutor_id' => $tutor->id, 'monto' => 1_000]);

    expect(app(CalculadorDeSaldoAFavor::class)->calcular($tutor))->toBe(1_000);
});

test('un tutor sin movimientos tiene saldo cero', function () {
    $tutor = Tutor::factory()->create();

    expect(app(CalculadorDeSaldoAFavor::class)->calcular($tutor))->toBe(0);
});
