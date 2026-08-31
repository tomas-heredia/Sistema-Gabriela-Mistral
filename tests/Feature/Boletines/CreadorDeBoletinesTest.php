<?php

use App\Alumnos\Models\Alumno;
use App\Boletines\Exceptions\PlantillaNoEncontradaException;
use App\Boletines\Models\Enums\EstadoTrimestre;
use App\Boletines\Models\PlantillaBoletin;
use App\Boletines\Services\CreadorDeBoletines;
use App\Core\Models\PeriodoLectivo;
use Illuminate\Database\QueryException;

test('crea el boletin con los 3 trimestres regulares en pendiente', function () {
    $periodo = PeriodoLectivo::factory()->create();
    $alumno = Alumno::factory()->primario()->create();
    $plantilla = PlantillaBoletin::factory()->create(['nivel' => $alumno->nivel, 'anio' => null]);

    $boletin = app(CreadorDeBoletines::class)->crear($alumno, $periodo);

    expect($boletin->plantilla_id)->toBe($plantilla->id)
        ->and($boletin->trimestres)->toHaveCount(3)
        ->and($boletin->trimestres->pluck('trimestre')->sort()->values()->all())->toBe([1, 2, 3])
        ->and($boletin->trimestres->every(fn ($t) => $t->estado === EstadoTrimestre::Pendiente))->toBeTrue();
});

test('selecciona la plantilla de secundaria segun el anio del alumno', function () {
    $periodo = PeriodoLectivo::factory()->create();
    $alumno = Alumno::factory()->secundario()->create(['anio_secundaria' => 3]);
    $plantillaCorrecta = PlantillaBoletin::factory()->secundaria(3)->create();
    PlantillaBoletin::factory()->secundaria(4)->create();

    $boletin = app(CreadorDeBoletines::class)->crear($alumno, $periodo);

    expect($boletin->plantilla_id)->toBe($plantillaCorrecta->id);
});

test('falla si no hay plantilla activa para el nivel/anio del alumno', function () {
    $periodo = PeriodoLectivo::factory()->create();
    $alumno = Alumno::factory()->primario()->create();

    app(CreadorDeBoletines::class)->crear($alumno, $periodo);
})->throws(PlantillaNoEncontradaException::class);

test('un alumno no puede tener dos boletines en el mismo periodo', function () {
    $periodo = PeriodoLectivo::factory()->create();
    $alumno = Alumno::factory()->primario()->create();
    PlantillaBoletin::factory()->create(['nivel' => $alumno->nivel, 'anio' => null]);

    app(CreadorDeBoletines::class)->crear($alumno, $periodo);

    app(CreadorDeBoletines::class)->crear($alumno, $periodo);
})->throws(QueryException::class);
