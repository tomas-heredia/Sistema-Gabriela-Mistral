<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Boletines\Mail\BoletinEnviado;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\Enums\EstadoBoletin;
use App\Boletines\Models\Enums\EstadoTrimestre;
use App\Boletines\Models\PlantillaBoletin;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

test('genera un pdf acumulativo y lo manda a todos los tutores con correo, no solo al responsable de pago', function () {
    Storage::fake('local');
    Mail::fake();

    $plantilla = PlantillaBoletin::factory()->create([
        'estructura_campos' => ['secciones' => [
            ['id' => 'promedio_anual', 'titulo' => 'Promedio Anual', 'tipo' => 'campo_numero'],
        ]],
    ]);
    $alumno = Alumno::factory()->create();
    $tutorPago = Tutor::factory()->create(['correo' => 'paga@example.com']);
    $tutorSinPago = Tutor::factory()->create(['correo' => 'noPaga@example.com']);
    $alumno->tutores()->attach($tutorPago, ['vinculo' => 'madre', 'responsable_pago' => true]);
    $alumno->tutores()->attach($tutorSinPago, ['vinculo' => 'padre', 'responsable_pago' => false]);

    $boletin = Boletin::factory()->create(['alumno_id' => $alumno->id, 'plantilla_id' => $plantilla->id]);
    $trimestre1 = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => 1,
        'datos' => ['promedio_anual' => 7],
        'estado' => EstadoTrimestre::Enviado,
        'pdf_path' => 'boletines/generados/'.$boletin->id.'/trimestre-1.pdf',
    ]);
    $trimestre2 = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => 2,
        'datos' => ['promedio_anual' => 8],
        'estado' => EstadoTrimestre::Cargado,
    ]);

    $trimestre2->confirmarYEnviar();

    $trimestre2->refresh();
    expect($trimestre2->estado)->toBe(EstadoTrimestre::Enviado)
        ->and($trimestre2->fecha_enviado)->not->toBeNull()
        ->and($trimestre2->pdf_path)->not->toBeNull();

    Storage::disk('local')->assertExists($trimestre2->pdf_path);
    $contenido = Storage::disk('local')->get($trimestre2->pdf_path);
    expect(str_starts_with($contenido, '%PDF'))->toBeTrue();

    Mail::assertSent(BoletinEnviado::class, function (BoletinEnviado $mail) {
        return $mail->hasTo('paga@example.com') && $mail->hasTo('noPaga@example.com');
    });
});

test('un alumno sin tutores vinculados no manda mail pero igual marca el trimestre como enviado', function () {
    Storage::fake('local');
    Mail::fake();

    $plantilla = PlantillaBoletin::factory()->create(['estructura_campos' => ['secciones' => []]]);
    $alumno = Alumno::factory()->create();
    $boletin = Boletin::factory()->create(['alumno_id' => $alumno->id, 'plantilla_id' => $plantilla->id]);
    $trimestre = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => 1,
        'estado' => EstadoTrimestre::Cargado,
        'datos' => [],
    ]);

    $trimestre->confirmarYEnviar();

    expect($trimestre->refresh()->estado)->toBe(EstadoTrimestre::Enviado);
    Mail::assertNothingSent();
});

test('el boletin pasa a completo cuando los 3 trimestres regulares estan enviados', function () {
    Storage::fake('local');
    Mail::fake();

    $plantilla = PlantillaBoletin::factory()->create(['estructura_campos' => ['secciones' => []]]);
    $boletin = Boletin::factory()->create(['plantilla_id' => $plantilla->id, 'estado' => EstadoBoletin::EnCurso]);
    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1, 'estado' => EstadoTrimestre::Enviado]);
    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 2, 'estado' => EstadoTrimestre::Enviado]);
    $trimestre3 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 3, 'estado' => EstadoTrimestre::Cargado, 'datos' => []]);

    $trimestre3->confirmarYEnviar();

    expect($boletin->fresh()->estado)->toBe(EstadoBoletin::Completo);
});
