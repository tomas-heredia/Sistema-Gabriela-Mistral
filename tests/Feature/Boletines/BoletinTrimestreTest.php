<?php

use App\Boletines\Exceptions\TransicionDeEstadoInvalidaException;
use App\Boletines\Jobs\GenerarYEnviarBoletinPdf;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\Enums\EstadoTrimestre;
use App\Boletines\Models\PlantillaBoletin;
use App\Core\Models\User;
use Illuminate\Support\Facades\Queue;

test('cargar guarda el borrador y pasa a estado cargado', function () {
    $plantilla = PlantillaBoletin::factory()->create();
    $trimestre = BoletinTrimestre::factory()->create();
    $trimestre->boletin->update(['plantilla_id' => $plantilla->id]);
    $usuario = User::factory()->create();

    $trimestre->cargar(['promedio_anual' => 8], $usuario);

    expect($trimestre->fresh()->estado)->toBe(EstadoTrimestre::Cargado)
        ->and($trimestre->fresh()->datos)->toBe(['promedio_anual' => 8])
        ->and($trimestre->fresh()->cargado_por_id)->toBe($usuario->id);
});

test('cargar rechaza una seccion que no existe en la plantilla', function () {
    $plantilla = PlantillaBoletin::factory()->create();
    $trimestre = BoletinTrimestre::factory()->create();
    $trimestre->boletin->update(['plantilla_id' => $plantilla->id]);

    $trimestre->cargar(['seccion_inventada' => 'x'], User::factory()->create());
})->throws(TransicionDeEstadoInvalidaException::class);

test('no se puede confirmar el envio de un trimestre que no fue cargado', function () {
    $trimestre = BoletinTrimestre::factory()->create(['estado' => EstadoTrimestre::Pendiente]);

    $trimestre->confirmarYEnviar();
})->throws(TransicionDeEstadoInvalidaException::class);

test('confirmar el envio de un trimestre cargado despacha el job de generacion', function () {
    Queue::fake();

    $trimestre = BoletinTrimestre::factory()->cargado()->create();

    $trimestre->confirmarYEnviar();

    Queue::assertPushed(GenerarYEnviarBoletinPdf::class, fn ($job) => $job->trimestre->is($trimestre));
});

test('no se puede editar un trimestre ya enviado', function () {
    $trimestre = BoletinTrimestre::factory()->create(['estado' => EstadoTrimestre::Enviado]);

    $trimestre->cargar(['promedio_anual' => 9], User::factory()->create());
})->throws(TransicionDeEstadoInvalidaException::class);
