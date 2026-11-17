<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Arancel;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\DescuentoTipo;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\TipoCuota;
use App\Cobranzas\Services\RecalculadorDeCuotas;
use App\Core\Models\PeriodoLectivo;

function periodoParaRecalculo(): PeriodoLectivo
{
    return PeriodoLectivo::factory()->create([
        'fecha_inicio' => '2026-03-01',
        'fecha_fin' => '2026-12-15',
    ]);
}

test('recalcularPorBeca con alcance "todas" recalcula las pendientes y exentas, no las parciales/pagadas/anuladas', function () {
    $periodo = periodoParaRecalculo();
    $alumno = Alumno::factory()->primario()->create();

    $exenta = Cuota::factory()->create([
        'alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id, 'mes' => 3,
        'monto_base' => 100_000, 'descuento_tipo' => DescuentoTipo::Beca, 'descuento_monto' => 100_000,
        'monto' => 0, 'estado' => EstadoCuota::Exenta,
    ]);
    $parcial = Cuota::factory()->create([
        'alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id, 'mes' => 4,
        'monto_base' => 100_000, 'descuento_tipo' => DescuentoTipo::Beca, 'descuento_monto' => 100_000,
        'monto' => 0, 'estado' => EstadoCuota::Parcial,
    ]);
    $anulada = Cuota::factory()->create([
        'alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id, 'mes' => 5,
        'monto_base' => 100_000, 'descuento_tipo' => DescuentoTipo::Beca, 'descuento_monto' => 100_000,
        'monto' => 0, 'estado' => EstadoCuota::Anulada,
    ]);

    app(RecalculadorDeCuotas::class)->recalcularPorBeca($alumno, $periodo);

    expect($exenta->fresh()->monto)->toBe(100_000)
        ->and($exenta->fresh()->descuento_tipo)->toBe(DescuentoTipo::Ninguno)
        ->and($exenta->fresh()->estado)->toBe(EstadoCuota::Pendiente)
        ->and($parcial->fresh()->monto)->toBe(0)
        ->and($parcial->fresh()->estado)->toBe(EstadoCuota::Parcial)
        ->and($anulada->fresh()->monto)->toBe(0)
        ->and($anulada->fresh()->estado)->toBe(EstadoCuota::Anulada);
});

test('recalcularPorBeca con fecha solo recalcula las cuotas que vencen desde esa fecha, inclusive', function () {
    $periodo = periodoParaRecalculo();
    $alumno = Alumno::factory()->primario()->create();

    $antes = Cuota::factory()->create([
        'alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id, 'mes' => 3,
        'monto_base' => 100_000, 'descuento_tipo' => DescuentoTipo::Beca, 'descuento_monto' => 100_000,
        'monto' => 0, 'estado' => EstadoCuota::Exenta, 'fecha_vencimiento' => '2026-04-09',
    ]);
    $exacta = Cuota::factory()->create([
        'alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id, 'mes' => 4,
        'monto_base' => 100_000, 'descuento_tipo' => DescuentoTipo::Beca, 'descuento_monto' => 100_000,
        'monto' => 0, 'estado' => EstadoCuota::Exenta, 'fecha_vencimiento' => '2026-04-10',
    ]);
    $despues = Cuota::factory()->create([
        'alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id, 'mes' => 5,
        'monto_base' => 100_000, 'descuento_tipo' => DescuentoTipo::Beca, 'descuento_monto' => 100_000,
        'monto' => 0, 'estado' => EstadoCuota::Exenta, 'fecha_vencimiento' => '2026-05-10',
    ]);

    app(RecalculadorDeCuotas::class)->recalcularPorBeca($alumno, $periodo, '2026-04-10');

    expect($antes->fresh()->monto)->toBe(0)
        ->and($exacta->fresh()->monto)->toBe(100_000)
        ->and($despues->fresh()->monto)->toBe(100_000);
});

test('recalcularPorBeca deja la cuota exenta por descuento de hermanos si el alumno sigue calificando sin la beca', function () {
    $periodo = periodoParaRecalculo();
    $tutor = Tutor::factory()->create();
    [$hermano1, $hermano2, $hermano3] = Alumno::factory()->primario()->count(3)->create()->each(
        fn (Alumno $alumno) => $alumno->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true])
    );

    // hermano3 ya califica como "3er hermano" (becado por esa regla), pero
    // además tenía una beca individual propia -- la beca individual es la
    // que se revoca, el descuento de hermanos tiene que seguir aplicando.
    $cuota = Cuota::factory()->create([
        'alumno_id' => $hermano3->id, 'periodo_lectivo_id' => $periodo->id,
        'monto_base' => 100_000, 'descuento_tipo' => DescuentoTipo::Beca, 'descuento_monto' => 100_000,
        'monto' => 0, 'estado' => EstadoCuota::Exenta,
    ]);

    app(RecalculadorDeCuotas::class)->recalcularPorBeca($hermano3->fresh(), $periodo);

    expect($cuota->fresh()->descuento_tipo)->toBe(DescuentoTipo::Hermanos)
        ->and($cuota->fresh()->monto)->toBe(0)
        ->and($cuota->fresh()->estado)->toBe(EstadoCuota::Exenta);
});

test('recalcularPorArancel actualiza monto_base y monto de las pendientes/exentas de ese periodo+nivel+tipo', function () {
    $periodo = periodoParaRecalculo();
    $alumno = Alumno::factory()->primario()->create();
    $arancel = Arancel::factory()->mensualidad()->create([
        'periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno->nivel, 'monto' => 110_000,
    ]);

    $pendiente = Cuota::factory()->create([
        'alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id, 'tipo' => TipoCuota::Mensualidad, 'mes' => 3,
        'monto_base' => 100_000, 'descuento_tipo' => DescuentoTipo::Ninguno, 'descuento_monto' => 0,
        'monto' => 100_000, 'estado' => EstadoCuota::Pendiente,
    ]);
    $becada = Cuota::factory()->create([
        'alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id, 'tipo' => TipoCuota::Mensualidad, 'mes' => 4,
        'monto_base' => 100_000, 'descuento_tipo' => DescuentoTipo::Beca, 'descuento_monto' => 100_000,
        'monto' => 0, 'estado' => EstadoCuota::Exenta,
    ]);
    $matriculaMismoAlumno = Cuota::factory()->matricula()->create([
        'alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id,
        'monto_base' => 150_000, 'descuento_tipo' => DescuentoTipo::Ninguno, 'descuento_monto' => 0,
        'monto' => 150_000, 'estado' => EstadoCuota::Pendiente,
    ]);

    app(RecalculadorDeCuotas::class)->recalcularPorArancel($arancel);

    expect($pendiente->fresh()->monto_base)->toBe(110_000)
        ->and($pendiente->fresh()->monto)->toBe(110_000)
        ->and($pendiente->fresh()->descuento_tipo)->toBe(DescuentoTipo::Ninguno)
        ->and($becada->fresh()->monto_base)->toBe(110_000)
        ->and($becada->fresh()->monto)->toBe(0)
        ->and($becada->fresh()->descuento_tipo)->toBe(DescuentoTipo::Beca)
        ->and($matriculaMismoAlumno->fresh()->monto_base)->toBe(150_000);
});

test('recalcularPorArancel no toca cuotas de otro nivel, otro periodo, ni parciales/pagadas', function () {
    $periodo = periodoParaRecalculo();
    $otroPeriodo = periodoParaRecalculo();
    $alumnoPrimario = Alumno::factory()->primario()->create();
    $alumnoSecundario = Alumno::factory()->secundario()->create();

    $arancelPrimario = Arancel::factory()->mensualidad()->create([
        'periodo_lectivo_id' => $periodo->id, 'nivel' => $alumnoPrimario->nivel, 'monto' => 110_000,
    ]);

    $deOtroNivel = Cuota::factory()->create([
        'alumno_id' => $alumnoSecundario->id, 'periodo_lectivo_id' => $periodo->id, 'tipo' => TipoCuota::Mensualidad,
        'monto_base' => 100_000, 'monto' => 100_000, 'estado' => EstadoCuota::Pendiente,
    ]);
    $deOtroPeriodo = Cuota::factory()->create([
        'alumno_id' => $alumnoPrimario->id, 'periodo_lectivo_id' => $otroPeriodo->id, 'tipo' => TipoCuota::Mensualidad,
        'monto_base' => 100_000, 'monto' => 100_000, 'estado' => EstadoCuota::Pendiente,
    ]);
    $pagada = Cuota::factory()->create([
        'alumno_id' => $alumnoPrimario->id, 'periodo_lectivo_id' => $periodo->id, 'tipo' => TipoCuota::Mensualidad,
        'monto_base' => 100_000, 'monto' => 100_000, 'estado' => EstadoCuota::Pagada,
    ]);

    app(RecalculadorDeCuotas::class)->recalcularPorArancel($arancelPrimario);

    expect($deOtroNivel->fresh()->monto_base)->toBe(100_000)
        ->and($deOtroPeriodo->fresh()->monto_base)->toBe(100_000)
        ->and($pagada->fresh()->monto_base)->toBe(100_000);
});
