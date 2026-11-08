<?php

use App\Alumnos\Models\Alumno;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\Enums\EstadoBoletin;
use App\Boletines\Models\Enums\EstadoTrimestre;
use App\Core\Models\User;
use Illuminate\Support\Facades\Storage;

test('eliminarConTrimestres borra el boletin, sus trimestres y los pdf generados en storage', function () {
    Storage::fake('local');

    $usuario = User::factory()->create();
    $boletin = Boletin::factory()->create();
    $trimestre1 = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => 1,
        'pdf_path' => "boletines/generados/{$boletin->id}/trimestre-1.pdf",
    ]);
    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 2]);
    Storage::disk('local')->put($trimestre1->pdf_path, '%PDF-fake');

    $boletin->eliminarConTrimestres($usuario);

    expect(Boletin::find($boletin->id))->toBeNull()
        ->and(BoletinTrimestre::where('boletin_id', $boletin->id)->count())->toBe(0);
    Storage::disk('local')->assertMissing($trimestre1->pdf_path);
});

test('primaria: recalcularEstado solo pasa a completo cuando la libreta final (trimestre 6) esta enviada', function () {
    $alumno = Alumno::factory()->primario()->create();
    $boletin = Boletin::factory()->create(['alumno_id' => $alumno->id, 'estado' => EstadoBoletin::EnCurso]);
    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1, 'estado' => EstadoTrimestre::Enviado]);
    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 2, 'estado' => EstadoTrimestre::Enviado]);
    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 3, 'estado' => EstadoTrimestre::Enviado]);

    $boletin->recalcularEstado();
    expect($boletin->fresh()->estado)->toBe(EstadoBoletin::EnCurso);

    $libretaFinal = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => BoletinTrimestre::TRIMESTRE_LIBRETA_FINAL,
        'estado' => EstadoTrimestre::Cargado,
    ]);

    $boletin->recalcularEstado();
    expect($boletin->fresh()->estado)->toBe(EstadoBoletin::EnCurso);

    $libretaFinal->update(['estado' => EstadoTrimestre::Enviado]);
    $boletin->recalcularEstado();
    expect($boletin->fresh()->estado)->toBe(EstadoBoletin::Completo);
});

test('secundaria: recalcularEstado pasa a completo apenas se envia el 3er trimestre, sin libreta final', function () {
    $alumno = Alumno::factory()->secundario()->create();
    $boletin = Boletin::factory()->create(['alumno_id' => $alumno->id, 'estado' => EstadoBoletin::EnCurso]);
    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1, 'estado' => EstadoTrimestre::Enviado]);
    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 2, 'estado' => EstadoTrimestre::Enviado]);

    $boletin->recalcularEstado();
    expect($boletin->fresh()->estado)->toBe(EstadoBoletin::EnCurso);

    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 3, 'estado' => EstadoTrimestre::Enviado]);

    $boletin->recalcularEstado();
    expect($boletin->fresh()->estado)->toBe(EstadoBoletin::Completo);
});
