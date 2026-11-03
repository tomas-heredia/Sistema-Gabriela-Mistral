<?php

use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
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
