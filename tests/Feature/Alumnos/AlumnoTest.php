<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Contrato;
use App\Alumnos\Models\Enums\Nivel;
use App\Alumnos\Models\Tutor;
use App\Core\Models\PeriodoLectivo;
use Illuminate\Database\QueryException;

test('la factory de alumno crea un registro valido', function () {
    $alumno = Alumno::factory()->create();

    expect($alumno->exists)->toBeTrue()
        ->and($alumno->nivel)->toBeInstanceOf(Nivel::class);
});

test('un alumno secundario tiene anio_secundaria, uno primario no', function () {
    $secundario = Alumno::factory()->secundario()->create();
    $primario = Alumno::factory()->primario()->create();

    expect($secundario->nivel)->toBe(Nivel::Secundario)
        ->and($secundario->anio_secundaria)->not->toBeNull()
        ->and($primario->nivel)->toBe(Nivel::Primario)
        ->and($primario->anio_secundaria)->toBeNull();
});

test('un alumno puede tener mas de un tutor, con vinculo y responsable_pago propios', function () {
    $alumno = Alumno::factory()->create();
    $madre = Tutor::factory()->create();
    $padre = Tutor::factory()->create();

    $alumno->tutores()->attach($madre, ['vinculo' => 'madre', 'responsable_pago' => true]);
    $alumno->tutores()->attach($padre, ['vinculo' => 'padre', 'responsable_pago' => false]);

    $tutores = $alumno->tutores()->get();

    expect($tutores)->toHaveCount(2);

    $tutorMadre = $tutores->firstWhere('id', $madre->id);
    expect($tutorMadre->pivot->vinculo)->toBe('madre')
        ->and((bool) $tutorMadre->pivot->responsable_pago)->toBeTrue();

    // La relación se lee también desde el tutor.
    expect($madre->fresh()->alumnos()->get())->toHaveCount(1);
});

test('un alumno tiene muchos contratos, uno por periodo lectivo', function () {
    $alumno = Alumno::factory()->create();
    $periodo2026 = PeriodoLectivo::factory()->create(['nombre' => '2026']);
    $periodo2027 = PeriodoLectivo::factory()->create(['nombre' => '2027']);

    Contrato::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo2026->id]);
    Contrato::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo2027->id]);

    expect($alumno->contratos()->count())->toBe(2);
});

test('el dni de un alumno es unico cuando no es nulo', function () {
    Alumno::factory()->create(['dni' => '30111222']);

    expect(fn () => Alumno::factory()->create(['dni' => '30111222']))
        ->toThrow(QueryException::class);
});

test('el dni de un tutor es unico', function () {
    Tutor::factory()->create(['dni' => '20333444']);

    expect(fn () => Tutor::factory()->create(['dni' => '20333444']))
        ->toThrow(QueryException::class);
});

test('no se puede vincular el mismo tutor dos veces al mismo alumno', function () {
    $alumno = Alumno::factory()->create();
    $tutor = Tutor::factory()->create();

    $alumno->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);

    expect(fn () => $alumno->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]))
        ->toThrow(QueryException::class);
});
