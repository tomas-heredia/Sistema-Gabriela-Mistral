<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Boletines\Jobs\GenerarYEnviarBoletinPdf;
use App\Boletines\Mail\BoletinEnviado;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\Enums\EstadoTrimestre;
use App\Boletines\Models\PlantillaBoletin;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Ejecuta el job de verdad (sin Queue::fake()) -- es exactamente el tipo de
 * bug que un fake no atrapa: la sección quedaba como lista vacía ("talleres"
 * sin cargar) y `valor-generico.blade.php` no la distinguía de un mapa/lista
 * con datos, así que caía al `{{ $valor }}` final e intentaba escapar un
 * array -- reventaba con un TypeError real de dompdf en producción, nunca
 * en un test que solo verificaba que el job se encolaba.
 */
test('genera el PDF sin romper cuando una seccion quedo como lista vacia', function () {
    Storage::fake('local');
    Mail::fake();

    $alumno = Alumno::factory()->create();
    $tutor = Tutor::factory()->create();
    $alumno->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);

    $boletin = Boletin::factory()->create([
        'alumno_id' => $alumno->id,
        'plantilla_id' => PlantillaBoletin::factory()->create()->id,
    ]);

    $trimestre = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => 1,
        'estado' => EstadoTrimestre::Cargado,
        'datos' => [
            'espacios_curriculares' => [],
            'promedio_anual' => 8,
        ],
    ]);

    (new GenerarYEnviarBoletinPdf($trimestre))->handle();

    expect($trimestre->fresh()->estado)->toBe(EstadoTrimestre::Enviado);

    Mail::assertSent(BoletinEnviado::class);
});
