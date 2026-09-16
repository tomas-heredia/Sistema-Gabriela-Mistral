<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Arancel;
use App\Cobranzas\Models\Beca;
use App\Cobranzas\Models\Cuota;
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

test('al matricular al 3er hermano se actualizan las cuotas futuras no vencidas de los otros 2', function () {
    $periodo = periodoDeAnio();
    $tutor = Tutor::factory()->create();
    $generador = app(GeneradorDeCuotas::class);

    $alumno1 = Alumno::factory()->primario()->create();
    $alumno1->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);
    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 150_000]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 100_000]);

    // Cada hermano genera sus cuotas apenas se lo matricula, como pasa de
    // verdad en la pantalla -- a diferencia del test de arriba, acá el
    // umbral recién se cruza al cargar el 3ro, no antes.
    $cuotas1 = $generador->generar($alumno1->fresh(), $periodo);
    expect($cuotas1->first()->descuento_tipo)->toBe(DescuentoTipo::Ninguno);

    $alumno2 = Alumno::factory()->primario()->create();
    $alumno2->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);
    $cuotas2 = $generador->generar($alumno2->fresh(), $periodo);
    expect($cuotas2->first()->descuento_tipo)->toBe(DescuentoTipo::Ninguno);

    $alumno3 = Alumno::factory()->primario()->create();
    $alumno3->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);
    $cuotas3 = $generador->generar($alumno3->fresh(), $periodo);

    expect($cuotas3->first()->descuento_tipo)->toBe(DescuentoTipo::Hermanos);

    foreach ([$alumno1, $alumno2] as $hermano) {
        $mensualidades = Cuota::where('alumno_id', $hermano->id)->where('tipo', TipoCuota::Mensualidad)->get();
        $futuras = $mensualidades->where('fecha_vencimiento', '>=', today());
        $vencidas = $mensualidades->where('fecha_vencimiento', '<', today());

        // A esta altura del período lectivo ya hay meses vencidos entre
        // marzo y hoy -- justamente lo que prueba este test: esos NO se
        // tocan, solo las cuotas todavía futuras.
        expect($futuras)->not->toBeEmpty()
            ->and($futuras->every(fn ($cuota) => $cuota->descuento_tipo === DescuentoTipo::Hermanos))->toBeTrue()
            ->and($futuras->first()->descuento_monto)->toBe((int) round(100_000 * 0.15))
            ->and($futuras->first()->monto)->toBe(100_000 - (int) round(100_000 * 0.15));

        expect($vencidas->every(fn ($cuota) => $cuota->descuento_tipo === DescuentoTipo::Ninguno))->toBeTrue();
    }
});

test('una cuota ya vencida del hermano no se toca al cruzar el umbral', function () {
    $periodo = periodoDeAnio();
    $tutor = Tutor::factory()->create();

    $alumno1 = Alumno::factory()->primario()->create();
    $alumno1->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);

    $cuotaVencida = Cuota::factory()->vencida()->create([
        'alumno_id' => $alumno1->id,
        'periodo_lectivo_id' => $periodo->id,
        'descuento_tipo' => DescuentoTipo::Ninguno,
        'descuento_monto' => 0,
        'monto_base' => 100_000,
        'monto' => 100_000,
    ]);

    $alumno2 = Alumno::factory()->primario()->create();
    $alumno2->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);

    $alumno3 = Alumno::factory()->primario()->create();
    $alumno3->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);
    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno3->nivel, 'monto' => 150_000]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno3->nivel, 'monto' => 100_000]);

    app(GeneradorDeCuotas::class)->generar($alumno3->fresh(), $periodo);

    expect($cuotaVencida->fresh()->descuento_tipo)->toBe(DescuentoTipo::Ninguno)
        ->and($cuotaVencida->fresh()->monto)->toBe(100_000);
});

test('un hermano con beca no se sobreescribe al cruzar el umbral', function () {
    $periodo = periodoDeAnio();
    $tutor = Tutor::factory()->create();

    $alumno1 = Alumno::factory()->primario()->create();
    $alumno1->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);
    Beca::factory()->create(['alumno_id' => $alumno1->id, 'periodo_lectivo_id' => $periodo->id]);

    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 150_000]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 100_000]);

    app(GeneradorDeCuotas::class)->generar($alumno1->fresh(), $periodo);

    $alumno2 = Alumno::factory()->primario()->create();
    $alumno2->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);

    $alumno3 = Alumno::factory()->primario()->create();
    $alumno3->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);

    app(GeneradorDeCuotas::class)->generar($alumno3->fresh(), $periodo);

    $cuotasAlumno1 = Cuota::where('alumno_id', $alumno1->id)->get();

    expect($cuotasAlumno1->every(fn ($cuota) => $cuota->descuento_tipo === DescuentoTipo::Beca))->toBeTrue()
        ->and($cuotasAlumno1->every(fn ($cuota) => $cuota->monto === 0))->toBeTrue();
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
