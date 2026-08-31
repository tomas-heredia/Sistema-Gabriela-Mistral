<?php

use App\Alumnos\Models\Enums\Nivel;
use App\Boletines\Exceptions\PlantillaActivaExistenteException;
use App\Boletines\Models\PlantillaBoletin;
use App\Boletines\Services\GestorDePlantillas;
use App\Core\Models\User;

test('crear una plantilla la deja activa en version 1', function () {
    $plantilla = app(GestorDePlantillas::class)->crear([
        'nivel' => Nivel::Primario,
        'anio' => null,
        'nombre' => 'Boletín primaria',
        'archivo' => 'boletines/plantillas/primaria.pdf',
        'estructura_campos' => ['secciones' => []],
        'creado_por_id' => User::factory()->create()->id,
    ]);

    expect($plantilla->version)->toBe(1)
        ->and($plantilla->activo)->toBeTrue()
        ->and($plantilla->reemplaza_a_id)->toBeNull();
});

test('no se puede crear una segunda plantilla activa para el mismo nivel y anio', function () {
    PlantillaBoletin::factory()->secundaria(2)->create();

    app(GestorDePlantillas::class)->crear([
        'nivel' => Nivel::Secundario,
        'anio' => 2,
        'nombre' => 'Otra',
        'archivo' => 'x.pdf',
        'estructura_campos' => ['secciones' => []],
        'creado_por_id' => User::factory()->create()->id,
    ]);
})->throws(PlantillaActivaExistenteException::class);

test('nuevaVersion crea una fila nueva y desactiva la anterior, sin romper la unicidad', function () {
    $v1 = PlantillaBoletin::factory()->create(['version' => 1]);

    $v2 = app(GestorDePlantillas::class)->nuevaVersion($v1, [
        'archivo' => 'boletines/plantillas/primaria-v2.pdf',
    ]);

    expect($v2->version)->toBe(2)
        ->and($v2->reemplaza_a_id)->toBe($v1->id)
        ->and($v2->activo)->toBeTrue()
        ->and($v2->nivel)->toBe($v1->nivel)
        ->and($v1->fresh()->activo)->toBeFalse();
});
