<?php

use App\Alumnos\Models\Alumno;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\Enums\EstadoTrimestre;
use App\Boletines\Services\GestorDeRecuperaciones;

function boletinConTrimestresRegulares(Alumno $alumno, array $notasPorTrimestre): Boletin
{
    $boletin = Boletin::factory()->create(['alumno_id' => $alumno->id]);

    foreach ($notasPorTrimestre as $numero => $materias) {
        $columnaId = "trimestre_{$numero}";
        BoletinTrimestre::factory()->create([
            'boletin_id' => $boletin->id,
            'trimestre' => $numero,
            'estado' => EstadoTrimestre::Enviado,
            'datos' => [
                'espacios_curriculares' => collect($materias)
                    ->map(fn ($nota, $nombre) => ['nombre' => $nombre, $columnaId => $nota])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    return $boletin;
}

test('primaria: una sola materia desaprobada en un solo trimestre ya genera la recuperacion de diciembre', function () {
    $alumno = Alumno::factory()->primario()->create();
    $boletin = boletinConTrimestresRegulares($alumno, [
        1 => ['Matemática' => 4, 'Lengua' => 8],
        2 => ['Matemática' => 7, 'Lengua' => 8],
        3 => ['Matemática' => 8, 'Lengua' => 8],
    ]);
    $trimestre3 = $boletin->trimestres->firstWhere('trimestre', 3);

    app(GestorDeRecuperaciones::class)->procesarEnvio($trimestre3);

    $diciembre = $boletin->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_RECUPERACION_DICIEMBRE)->first();
    expect($diciembre)->not->toBeNull()
        ->and(collect($diciembre->datos['espacios_curriculares'])->pluck('nombre')->all())->toBe(['Matemática']);
});

test('secundaria: una sola materia desaprobada en un trimestre que no es el 3ro no alcanza', function () {
    $alumno = Alumno::factory()->secundario()->create();
    $boletin = boletinConTrimestresRegulares($alumno, [
        1 => ['Matemática' => 4],
        2 => ['Matemática' => 8],
        3 => ['Matemática' => 8],
    ]);
    $trimestre3 = $boletin->trimestres->firstWhere('trimestre', 3);

    app(GestorDeRecuperaciones::class)->procesarEnvio($trimestre3);

    expect($boletin->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_RECUPERACION_DICIEMBRE)->exists())->toBeFalse()
        ->and($boletin->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_LIBRETA_FINAL)->exists())->toBeTrue();
});

test('secundaria: dos trimestres desaprobados generan la recuperacion aunque el 3ro este bien', function () {
    $alumno = Alumno::factory()->secundario()->create();
    $boletin = boletinConTrimestresRegulares($alumno, [
        1 => ['Matemática' => 4],
        2 => ['Matemática' => 4],
        3 => ['Matemática' => 8],
    ]);
    $trimestre3 = $boletin->trimestres->firstWhere('trimestre', 3);

    app(GestorDeRecuperaciones::class)->procesarEnvio($trimestre3);

    expect($boletin->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_RECUPERACION_DICIEMBRE)->exists())->toBeTrue();
});

test('secundaria: el 3er trimestre desaprobado solo ya alcanza, aunque los otros dos esten bien', function () {
    $alumno = Alumno::factory()->secundario()->create();
    $boletin = boletinConTrimestresRegulares($alumno, [
        1 => ['Matemática' => 8],
        2 => ['Matemática' => 8],
        3 => ['Matemática' => 4],
    ]);
    $trimestre3 = $boletin->trimestres->firstWhere('trimestre', 3);

    app(GestorDeRecuperaciones::class)->procesarEnvio($trimestre3);

    expect($boletin->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_RECUPERACION_DICIEMBRE)->exists())->toBeTrue();
});

test('sin materias desaprobadas se genera la libreta final directo, con el promedio de cada materia', function () {
    $alumno = Alumno::factory()->primario()->create();
    $boletin = boletinConTrimestresRegulares($alumno, [
        1 => ['Lengua' => 6, 'Matemática' => 10],
        2 => ['Lengua' => 8, 'Matemática' => 10],
        3 => ['Lengua' => 10, 'Matemática' => 10],
    ]);
    $trimestre3 = $boletin->trimestres->firstWhere('trimestre', 3);

    app(GestorDeRecuperaciones::class)->procesarEnvio($trimestre3);

    $final = $boletin->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_LIBRETA_FINAL)->first();
    $filas = collect($final->datos['espacios_curriculares'])->keyBy('nombre');

    expect($filas['Lengua']['nota_final'])->toBe('8')
        ->and($filas['Matemática']['nota_final'])->toBe('10');
});

test('diciembre que aprueba todo genera la libreta final en vez de febrero', function () {
    $alumno = Alumno::factory()->primario()->create();
    $boletin = boletinConTrimestresRegulares($alumno, [1 => ['Matemática' => 4], 2 => ['Matemática' => 8], 3 => ['Matemática' => 8]]);
    $diciembre = BoletinTrimestre::create([
        'boletin_id' => $boletin->id,
        'trimestre' => BoletinTrimestre::TRIMESTRE_RECUPERACION_DICIEMBRE,
        'estado' => EstadoTrimestre::Enviado,
        'datos' => ['espacios_curriculares' => [['nombre' => 'Matemática', 'nota_diciembre' => '7']]],
    ]);

    app(GestorDeRecuperaciones::class)->procesarEnvio($diciembre);

    expect($boletin->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_RECUPERACION_FEBRERO)->exists())->toBeFalse()
        ->and($boletin->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_LIBRETA_FINAL)->exists())->toBeTrue();
});

test('diciembre que no aprueba todo genera febrero solo con lo que sigue desaprobado', function () {
    $alumno = Alumno::factory()->primario()->create();
    $boletin = boletinConTrimestresRegulares($alumno, [1 => ['Matemática' => 4, 'Lengua' => 4], 2 => ['Matemática' => 8, 'Lengua' => 8], 3 => ['Matemática' => 8, 'Lengua' => 8]]);
    $diciembre = BoletinTrimestre::create([
        'boletin_id' => $boletin->id,
        'trimestre' => BoletinTrimestre::TRIMESTRE_RECUPERACION_DICIEMBRE,
        'estado' => EstadoTrimestre::Enviado,
        'datos' => ['espacios_curriculares' => [
            ['nombre' => 'Matemática', 'nota_diciembre' => '5'],
            ['nombre' => 'Lengua', 'nota_diciembre' => '7'],
        ]],
    ]);

    app(GestorDeRecuperaciones::class)->procesarEnvio($diciembre);

    $febrero = $boletin->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_RECUPERACION_FEBRERO)->first();
    expect($febrero)->not->toBeNull()
        ->and(collect($febrero->datos['espacios_curriculares'])->pluck('nombre')->all())->toBe(['Matemática']);
});

test('febrero siempre genera la libreta final, apruebe o no', function () {
    $alumno = Alumno::factory()->primario()->create();
    $boletin = boletinConTrimestresRegulares($alumno, [1 => ['Matemática' => 4], 2 => ['Matemática' => 8], 3 => ['Matemática' => 8]]);
    $febrero = BoletinTrimestre::create([
        'boletin_id' => $boletin->id,
        'trimestre' => BoletinTrimestre::TRIMESTRE_RECUPERACION_FEBRERO,
        'estado' => EstadoTrimestre::Enviado,
        'datos' => ['espacios_curriculares' => [['nombre' => 'Matemática', 'nota_febrero' => '3']]],
    ]);

    app(GestorDeRecuperaciones::class)->procesarEnvio($febrero);

    $final = $boletin->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_LIBRETA_FINAL)->first();
    expect($final)->not->toBeNull()
        ->and(collect($final->datos['espacios_curriculares'])->firstWhere('nombre', 'Matemática')['nota_final'])->toBe('3');
});

test('la libreta final usa la nota de febrero si existe, si no la de diciembre, si no el promedio de los 3 trimestres', function () {
    $alumno = Alumno::factory()->primario()->create();
    $boletin = boletinConTrimestresRegulares($alumno, [
        1 => ['SoloDiciembre' => 4, 'SoloFebrero' => 4, 'SinRecuperacion' => 6],
        2 => ['SoloDiciembre' => 8, 'SoloFebrero' => 4, 'SinRecuperacion' => 8],
        3 => ['SoloDiciembre' => 8, 'SoloFebrero' => 8, 'SinRecuperacion' => 10],
    ]);
    BoletinTrimestre::create([
        'boletin_id' => $boletin->id,
        'trimestre' => BoletinTrimestre::TRIMESTRE_RECUPERACION_DICIEMBRE,
        'estado' => EstadoTrimestre::Enviado,
        'datos' => ['espacios_curriculares' => [
            ['nombre' => 'SoloDiciembre', 'nota_diciembre' => '9'],
            ['nombre' => 'SoloFebrero', 'nota_diciembre' => '5'],
        ]],
    ]);
    $febrero = BoletinTrimestre::create([
        'boletin_id' => $boletin->id,
        'trimestre' => BoletinTrimestre::TRIMESTRE_RECUPERACION_FEBRERO,
        'estado' => EstadoTrimestre::Enviado,
        'datos' => ['espacios_curriculares' => [['nombre' => 'SoloFebrero', 'nota_febrero' => '6']]],
    ]);

    app(GestorDeRecuperaciones::class)->procesarEnvio($febrero);

    $filas = collect($boletin->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_LIBRETA_FINAL)->first()->datos['espacios_curriculares'])->keyBy('nombre');

    expect($filas['SoloDiciembre']['nota_final'])->toBe('9')
        ->and($filas['SoloFebrero']['nota_final'])->toBe('6')
        ->and($filas['SinRecuperacion']['nota_final'])->toBe('8');
});

test('procesarEnvio es idempotente: no duplica la recuperacion ni la libreta final si ya existen', function () {
    $alumno = Alumno::factory()->primario()->create();
    $boletin = boletinConTrimestresRegulares($alumno, [1 => ['Matemática' => 4], 2 => ['Matemática' => 8], 3 => ['Matemática' => 8]]);
    $trimestre3 = $boletin->trimestres->firstWhere('trimestre', 3);

    $gestor = app(GestorDeRecuperaciones::class);
    $gestor->procesarEnvio($trimestre3);
    $gestor->procesarEnvio($trimestre3);

    expect($boletin->trimestres()->where('trimestre', BoletinTrimestre::TRIMESTRE_RECUPERACION_DICIEMBRE)->count())->toBe(1);
});

test('las secciones de materias de las 7 plantillas no tienen nombres de fila duplicados', function () {
    $base = database_path('seeders/data/plantillas_boletin');

    foreach (['primaria', 'secundaria_1', 'secundaria_2', 'secundaria_3', 'secundaria_4', 'secundaria_5', 'secundaria_6'] as $clave) {
        $json = json_decode(file_get_contents("{$base}/{$clave}.json"), associative: true);
        $seccion = collect($json['secciones'])->firstWhere('id', 'espacios_curriculares');

        $nombres = collect($seccion['filas'])
            ->flatMap(fn ($fila) => $fila['subfilas'] ?? [$fila])
            ->pluck('nombre');

        expect($nombres->duplicates())->toBeEmpty("la plantilla {$clave} tiene nombres de materia duplicados");
    }
});
