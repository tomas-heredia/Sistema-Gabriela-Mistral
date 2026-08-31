<?php

use App\Boletines\Models\Boletin;
use App\Boletines\Models\PlantillaBoletin;
use Illuminate\Database\QueryException;

test('no se puede borrar una plantilla que ya tiene boletines asociados', function () {
    $plantilla = PlantillaBoletin::factory()->create();
    Boletin::factory()->create(['plantilla_id' => $plantilla->id]);

    $plantilla->delete();
})->throws(QueryException::class);

test('se puede borrar una plantilla sin boletines asociados', function () {
    $plantilla = PlantillaBoletin::factory()->create();

    $plantilla->delete();

    expect(PlantillaBoletin::find($plantilla->id))->toBeNull();
});
