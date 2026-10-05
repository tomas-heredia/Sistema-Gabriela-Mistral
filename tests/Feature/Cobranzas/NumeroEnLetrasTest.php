<?php

use App\Cobranzas\Services\NumeroEnLetras;

/**
 * No usa NumberFormatter::SPELLOUT a propósito -- el ICU de Alpine (la
 * imagen que corre en el VPS) no trae las reglas de deletreo en español, así
 * que en producción el "SON: ..." salía en inglés ("SIXTY-THREE THOUSAND
 * PESOS") aunque en Windows funcionaba bien. Estos casos cubren justo las
 * irregularidades del español que un conversor genérico suele romper.
 *
 * Los montos se escriben como "pesos * 100" (centavos) en vez de escribir
 * el entero a mano, para no equivocarse contando ceros.
 */
test('numeros simples', function (int $pesos, string $esperado) {
    expect(NumeroEnLetras::pesos($pesos * 100))->toBe($esperado);
})->with([
    [0, 'CERO PESOS'],
    [1, 'UN PESO'],
    [2, 'DOS PESOS'],
    [5, 'CINCO PESOS'],
    [10, 'DIEZ PESOS'],
    [11, 'ONCE PESOS'],
    [19, 'DIECINUEVE PESOS'],
]);

test('la decena veinte es un caso especial sin apocope', function () {
    expect(NumeroEnLetras::pesos(20 * 100))->toBe('VEINTE PESOS');
});

test('veintiuno se escribe veintiun con tilde, no veintiuno ni veintiun sin tilde', function () {
    expect(NumeroEnLetras::pesos(21 * 100))->toBe('VEINTIÚN PESOS')
        ->and(NumeroEnLetras::pesos(29 * 100))->toBe('VEINTINUEVE PESOS');
});

test('decenas de treinta en adelante usan "y" entre decena y unidad', function (int $pesos, string $esperado) {
    expect(NumeroEnLetras::pesos($pesos * 100))->toBe($esperado);
})->with([
    [30, 'TREINTA PESOS'],
    [31, 'TREINTA Y UN PESOS'],
    [45, 'CUARENTA Y CINCO PESOS'],
    [99, 'NOVENTA Y NUEVE PESOS'],
]);

test('cien es exacto pero ciento antecede al resto', function (int $pesos, string $esperado) {
    expect(NumeroEnLetras::pesos($pesos * 100))->toBe($esperado);
})->with([
    [100, 'CIEN PESOS'],
    [101, 'CIENTO UN PESOS'],
    [150, 'CIENTO CINCUENTA PESOS'],
]);

test('centenas irregulares (quinientos, no cincocientos)', function (int $pesos, string $esperado) {
    expect(NumeroEnLetras::pesos($pesos * 100))->toBe($esperado);
})->with([
    [200, 'DOSCIENTOS PESOS'],
    [500, 'QUINIENTOS PESOS'],
    [700, 'SETECIENTOS PESOS'],
    [999, 'NOVECIENTOS NOVENTA Y NUEVE PESOS'],
]);

test('mil no lleva "un" adelante, pero los miles compuestos si lo apocopan', function (int $pesos, string $esperado) {
    expect(NumeroEnLetras::pesos($pesos * 100))->toBe($esperado);
})->with([
    [1_000, 'MIL PESOS'],
    [1_500, 'MIL QUINIENTOS PESOS'],
    [1_999, 'MIL NOVECIENTOS NOVENTA Y NUEVE PESOS'],
]);

test('el caso real que salio mal en produccion: sesenta y tres mil', function () {
    // $60.000 de subtotal + $3.000 (5% de interes) = $63.000 -- el pago
    // real que disparo el bug reportado.
    expect(NumeroEnLetras::pesos(63_000 * 100))->toBe('SESENTA Y TRES MIL PESOS');
});

test('miles que terminan en veintiuno tambien llevan tilde', function () {
    expect(NumeroEnLetras::pesos(21_000 * 100))->toBe('VEINTIÚN MIL PESOS');
});

test('centenas de miles', function (int $pesos, string $esperado) {
    expect(NumeroEnLetras::pesos($pesos * 100))->toBe($esperado);
})->with([
    [500_000, 'QUINIENTOS MIL PESOS'],
    [999_999, 'NOVECIENTOS NOVENTA Y NUEVE MIL NOVECIENTOS NOVENTA Y NUEVE PESOS'],
]);

test('millones', function (int $pesos, string $esperado) {
    expect(NumeroEnLetras::pesos($pesos * 100))->toBe($esperado);
})->with([
    [1_000_000, 'UN MILLÓN PESOS'],
    [2_000_000, 'DOS MILLONES PESOS'],
    [21_000_000, 'VEINTIÚN MILLONES PESOS'],
    [1_500_000, 'UN MILLÓN QUINIENTOS MIL PESOS'],
]);

test('centavos se agregan con CON y singular/plural correcto', function (int $centavos, string $esperado) {
    expect(NumeroEnLetras::pesos($centavos))->toBe($esperado);
})->with([
    [1_050 * 100 + 1, 'MIL CINCUENTA PESOS CON UN CENTAVO'],
    [1_050 * 100 + 50, 'MIL CINCUENTA PESOS CON CINCUENTA CENTAVOS'],
    [1, 'CERO PESOS CON UN CENTAVO'],
]);
