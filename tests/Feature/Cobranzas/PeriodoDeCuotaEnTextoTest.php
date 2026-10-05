<?php

use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\TipoCuota;
use App\Cobranzas\Services\PeriodoDeCuotaEnTexto;
use App\Core\Models\PeriodoLectivo;

test('una mensualidad muestra mes/periodo', function () {
    $periodo = PeriodoLectivo::factory()->create(['nombre' => '2026']);
    $cuota = Cuota::factory()->create([
        'periodo_lectivo_id' => $periodo->id,
        'tipo' => TipoCuota::Mensualidad,
        'mes' => 3,
    ]);

    expect(PeriodoDeCuotaEnTexto::calcular($cuota))->toBe('03/2026');
});

test('una matricula no muestra "00/periodo" -- no es un mes real', function () {
    $periodo = PeriodoLectivo::factory()->create(['nombre' => '2026']);
    $cuota = Cuota::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id]);

    expect(PeriodoDeCuotaEnTexto::calcular($cuota))->toBe('Matrícula 2026');
});
