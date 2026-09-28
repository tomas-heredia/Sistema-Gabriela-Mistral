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
use Illuminate\Support\Collection;

function periodoDeAnio(): PeriodoLectivo
{
    return PeriodoLectivo::factory()->create([
        'fecha_inicio' => '2026-03-01',
        'fecha_fin' => '2026-12-15',
    ]);
}

/** Crea $cantidad alumnos y los vincula, en orden, al mismo tutor responsable de pago. */
function familiaDeHermanos(Tutor $tutor, int $cantidad): Collection
{
    return Alumno::factory()->primario()->count($cantidad)->create()->each(
        fn (Alumno $alumno) => $alumno->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true])
    );
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

test('con 1 o 2 hermanos matriculados no hay beca por hermano', function () {
    $periodo = periodoDeAnio();
    $tutor = Tutor::factory()->create();
    [$alumno1, $alumno2] = familiaDeHermanos($tutor, 2);

    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 150_000]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 100_000]);

    $generador = app(GeneradorDeCuotas::class);
    $cuotas1 = $generador->generar($alumno1->fresh(), $periodo);
    $cuotas2 = $generador->generar($alumno2->fresh(), $periodo);

    expect($cuotas1->first()->descuento_tipo)->toBe(DescuentoTipo::Ninguno)
        ->and($cuotas1->first()->descuento_monto)->toBe(0)
        ->and($cuotas2->first()->descuento_tipo)->toBe(DescuentoTipo::Ninguno);
});

test('con 3 hermanos, solo el 3ro por orden de alta tiene beca completa -- los otros 2 pagan el 100%', function () {
    $periodo = periodoDeAnio();
    $tutor = Tutor::factory()->create();
    [$alumno1, $alumno2, $alumno3] = familiaDeHermanos($tutor, 3);

    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 150_000]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 100_000]);

    $generador = app(GeneradorDeCuotas::class);
    $cuotas1 = $generador->generar($alumno1->fresh(), $periodo);
    $cuotas2 = $generador->generar($alumno2->fresh(), $periodo);
    $cuotas3 = $generador->generar($alumno3->fresh(), $periodo);

    expect($cuotas1->every(fn ($c) => $c->descuento_tipo === DescuentoTipo::Ninguno))->toBeTrue()
        ->and($cuotas2->every(fn ($c) => $c->descuento_tipo === DescuentoTipo::Ninguno))->toBeTrue();

    expect($cuotas3->every(fn ($c) => $c->descuento_tipo === DescuentoTipo::Hermanos))->toBeTrue()
        ->and($cuotas3->every(fn ($c) => $c->monto === 0))->toBeTrue()
        ->and($cuotas3->every(fn ($c) => $c->estado === EstadoCuota::Exenta))->toBeTrue();
});

test('el orden en que se generan las cuotas no importa, solo el orden de alta del alumno', function () {
    $periodo = periodoDeAnio();
    $tutor = Tutor::factory()->create();
    [$alumno1, $alumno2, $alumno3] = familiaDeHermanos($tutor, 3);

    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 150_000]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 100_000]);

    // Se generan en orden inverso al de alta -- el cobrador puede tildar
    // los botones en cualquier orden, no necesariamente el de alta.
    $generador = app(GeneradorDeCuotas::class);
    $cuotas3 = $generador->generar($alumno3->fresh(), $periodo);
    $cuotas2 = $generador->generar($alumno2->fresh(), $periodo);
    $cuotas1 = $generador->generar($alumno1->fresh(), $periodo);

    expect($cuotas1->first()->descuento_tipo)->toBe(DescuentoTipo::Ninguno)
        ->and($cuotas2->first()->descuento_tipo)->toBe(DescuentoTipo::Ninguno)
        ->and($cuotas3->first()->descuento_tipo)->toBe(DescuentoTipo::Hermanos);
});

test('con 4 hermanos el 4to no esta becado -- solo el 3ro', function () {
    $periodo = periodoDeAnio();
    $tutor = Tutor::factory()->create();
    [$alumno1, $alumno2, $alumno3, $alumno4] = familiaDeHermanos($tutor, 4);

    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 150_000]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 100_000]);

    $generador = app(GeneradorDeCuotas::class);
    foreach ([$alumno1, $alumno2, $alumno3, $alumno4] as $alumno) {
        $generador->generar($alumno->fresh(), $periodo);
    }

    $tipos = collect([$alumno1, $alumno2, $alumno3, $alumno4])
        ->map(fn ($a) => Cuota::where('alumno_id', $a->id)->where('tipo', TipoCuota::Matricula)->first()->descuento_tipo);

    // 4 hermanos pagan 3 -- exactamente 1 becado (el 3ro), no 2.
    expect($tipos->all())->toBe([
        DescuentoTipo::Ninguno, DescuentoTipo::Ninguno, DescuentoTipo::Hermanos, DescuentoTipo::Ninguno,
    ]);
});

test('con 6 hermanos sigue habiendo exactamente 1 becado, no escala con la cantidad', function () {
    $periodo = periodoDeAnio();
    $tutor = Tutor::factory()->create();
    $hermanos = familiaDeHermanos($tutor, 6);

    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $hermanos->first()->nivel, 'monto' => 150_000]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $hermanos->first()->nivel, 'monto' => 100_000]);

    $generador = app(GeneradorDeCuotas::class);
    foreach ($hermanos as $alumno) {
        $generador->generar($alumno->fresh(), $periodo);
    }

    // 6 hermanos pagan 5 (pedido explicito del cliente): un solo cupo
    // gratis, sin importar cuantos hermanos haya en total.
    $becados = Cuota::whereIn('alumno_id', $hermanos->pluck('id'))
        ->where('tipo', TipoCuota::Matricula)
        ->where('descuento_tipo', DescuentoTipo::Hermanos)
        ->count();

    expect($becados)->toBe(1);
});

test('las cuotas del 1ro y 2do hermano no se tocan cuando llega el 3ro', function () {
    $periodo = periodoDeAnio();
    $tutor = Tutor::factory()->create();
    [$alumno1, $alumno2, $alumno3] = familiaDeHermanos($tutor, 3);

    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 150_000]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 100_000]);

    $generador = app(GeneradorDeCuotas::class);
    $cuotas1Antes = $generador->generar($alumno1->fresh(), $periodo)->pluck('id');
    $cuotas2Antes = $generador->generar($alumno2->fresh(), $periodo)->pluck('id');

    $generador->generar($alumno3->fresh(), $periodo);

    $cuotas1 = Cuota::whereIn('id', $cuotas1Antes)->get();
    $cuotas2 = Cuota::whereIn('id', $cuotas2Antes)->get();

    expect($cuotas1->every(fn ($c) => $c->descuento_tipo === DescuentoTipo::Ninguno))->toBeTrue()
        ->and($cuotas2->every(fn ($c) => $c->descuento_tipo === DescuentoTipo::Ninguno))->toBeTrue();
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

test('si el que seria el 3er hermano ya tiene beca individual, esa beca gana y queda como Beca', function () {
    $periodo = periodoDeAnio();
    $tutor = Tutor::factory()->create();
    [$alumno1, $alumno2, $alumno3] = familiaDeHermanos($tutor, 3);
    Beca::factory()->create(['alumno_id' => $alumno3->id, 'periodo_lectivo_id' => $periodo->id]);

    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 150_000]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno1->nivel, 'monto' => 100_000]);

    $generador = app(GeneradorDeCuotas::class);
    $generador->generar($alumno1->fresh(), $periodo);
    $generador->generar($alumno2->fresh(), $periodo);
    $cuotas3 = $generador->generar($alumno3->fresh(), $periodo);

    expect($cuotas3->every(fn ($c) => $c->descuento_tipo === DescuentoTipo::Beca))->toBeTrue();

    $cuotas1 = Cuota::where('alumno_id', $alumno1->id)->get();
    $cuotas2 = Cuota::where('alumno_id', $alumno2->id)->get();
    expect($cuotas1->every(fn ($c) => $c->descuento_tipo === DescuentoTipo::Ninguno))->toBeTrue()
        ->and($cuotas2->every(fn ($c) => $c->descuento_tipo === DescuentoTipo::Ninguno))->toBeTrue();
});
