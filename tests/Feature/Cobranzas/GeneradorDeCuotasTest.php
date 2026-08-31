<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Arancel;
use App\Cobranzas\Models\Beca;
use App\Cobranzas\Models\Enums\DescuentoTipo;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\TipoCuota;
use App\Cobranzas\Services\GeneradorDeCuotas;
use App\Core\Models\PeriodoLectivo;

function periodoDeAnio(): PeriodoLectivo
{
    return PeriodoLectivo::factory()->create([
        'fecha_inicio' => '2026-03-01',
        'fecha_fin' => '2026-12-15',
        'descuento_hermanos_pct' => 15,
    ]);
}

test('genera matricula + una cuota por cada mes del periodo, sin descuento por defecto', function () {
    $periodo = periodoDeAnio();
    $alumno = Alumno::factory()->primario()->create();
    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno->nivel, 'monto' => 150_000]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno->nivel, 'monto' => 100_000]);

    $cuotas = app(GeneradorDeCuotas::class)->generar($alumno, $periodo);

    // marzo a diciembre = 10 meses + 1 matrícula
    expect($cuotas)->toHaveCount(11);

    $matricula = $cuotas->firstWhere('tipo', TipoCuota::Matricula);
    expect($matricula->monto)->toBe(150_000)
        ->and($matricula->descuento_tipo)->toBe(DescuentoTipo::Ninguno)
        ->and($matricula->estado)->toBe(EstadoCuota::Pendiente);

    $mensualidades = $cuotas->where('tipo', TipoCuota::Mensualidad);
    expect($mensualidades)->toHaveCount(10)
        ->and($mensualidades->pluck('mes')->sort()->values()->all())->toBe(range(3, 12))
        ->and($mensualidades->first()->monto)->toBe(100_000);
});

test('con 1 o 2 hermanos matriculados no se aplica descuento', function () {
    $periodo = periodoDeAnio();
    $tutor = Tutor::factory()->create();

    $alumno1 = Alumno::factory()->primario()->create();
    $alumno2 = Alumno::factory()->primario()->create();
    $alumno1->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);
    $alumno2->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);

    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 150_000]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 100_000]);

    $cuotas = app(GeneradorDeCuotas::class)->generar($alumno1->fresh(), $periodo);

    expect($cuotas->first()->descuento_tipo)->toBe(DescuentoTipo::Ninguno)
        ->and($cuotas->first()->descuento_monto)->toBe(0);
});

test('con 3 o mas hermanos matriculados el descuento se aplica a los 3, incluidos el 1ro y 2do', function () {
    $periodo = periodoDeAnio();
    $tutor = Tutor::factory()->create();

    $alumnos = Alumno::factory()->primario()->count(3)->create();
    foreach ($alumnos as $alumno) {
        $alumno->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);
    }

    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumnos->first()->nivel, 'monto' => 150_000]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumnos->first()->nivel, 'monto' => 100_000]);

    $generador = app(GeneradorDeCuotas::class);

    foreach ($alumnos as $alumno) {
        $cuotas = $generador->generar($alumno->fresh(), $periodo);
        $matricula = $cuotas->firstWhere('tipo', TipoCuota::Matricula);

        expect($matricula->descuento_tipo)->toBe(DescuentoTipo::Hermanos)
            ->and($matricula->descuento_monto)->toBe((int) round(150_000 * 0.15))
            ->and($matricula->monto)->toBe(150_000 - (int) round(150_000 * 0.15));
    }
});

test('un alumno con beca tiene todas sus cuotas en monto 0 y estado exenta', function () {
    $periodo = periodoDeAnio();
    $alumno = Alumno::factory()->primario()->create();
    Beca::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id]);

    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno->nivel, 'monto' => 150_000]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno->nivel, 'monto' => 100_000]);

    $cuotas = app(GeneradorDeCuotas::class)->generar($alumno, $periodo);

    expect($cuotas->every(fn ($cuota) => $cuota->monto === 0))->toBeTrue()
        ->and($cuotas->every(fn ($cuota) => $cuota->estado === EstadoCuota::Exenta))->toBeTrue()
        ->and($cuotas->every(fn ($cuota) => $cuota->descuento_tipo === DescuentoTipo::Beca))->toBeTrue();
});

test('la beca gana si el alumno tambien tiene 3 o mas hermanos', function () {
    $periodo = periodoDeAnio();
    $tutor = Tutor::factory()->create();
    $alumnos = Alumno::factory()->primario()->count(3)->create();
    foreach ($alumnos as $alumno) {
        $alumno->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);
    }
    Beca::factory()->create(['alumno_id' => $alumnos->first()->id, 'periodo_lectivo_id' => $periodo->id]);

    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumnos->first()->nivel, 'monto' => 150_000]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumnos->first()->nivel, 'monto' => 100_000]);

    $cuotas = app(GeneradorDeCuotas::class)->generar($alumnos->first()->fresh(), $periodo);

    expect($cuotas->every(fn ($cuota) => $cuota->descuento_tipo === DescuentoTipo::Beca))->toBeTrue();
});
