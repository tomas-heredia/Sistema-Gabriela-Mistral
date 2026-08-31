<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Contrato;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Illuminate\Database\QueryException;

test('la factory de contrato crea un registro valido con sus relaciones', function () {
    $contrato = Contrato::factory()->create();

    expect($contrato->exists)->toBeTrue()
        ->and($contrato->alumno)->toBeInstanceOf(Alumno::class)
        ->and($contrato->periodoLectivo)->toBeInstanceOf(PeriodoLectivo::class)
        ->and($contrato->cargadoPor)->toBeInstanceOf(User::class);
});

test('un alumno no puede tener dos contratos en el mismo periodo lectivo', function () {
    $alumno = Alumno::factory()->create();
    $periodo = PeriodoLectivo::factory()->create();

    Contrato::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id]);

    expect(fn () => Contrato::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id]))
        ->toThrow(QueryException::class);
});
