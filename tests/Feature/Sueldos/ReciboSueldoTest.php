<?php

use App\Sueldos\Models\ReciboSueldo;
use Illuminate\Database\QueryException;

test('la factory de recibo de sueldo crea un registro valido', function () {
    $recibo = ReciboSueldo::factory()->create();

    expect($recibo->exists)->toBeTrue();
});

test('un profesor no puede tener dos recibos en el mismo periodo', function () {
    $recibo = ReciboSueldo::factory()->create(['periodo' => '2026-03']);

    expect(fn () => ReciboSueldo::factory()->create(['profesor_id' => $recibo->profesor_id, 'periodo' => '2026-03']))
        ->toThrow(QueryException::class);
});
