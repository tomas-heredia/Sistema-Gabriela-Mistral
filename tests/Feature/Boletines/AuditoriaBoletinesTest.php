<?php

use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\Enums\EstadoTrimestre;
use App\Boletines\Models\PlantillaBoletin;
use App\Core\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/**
 * No hay pantalla para esta auditoría (ver BoletinTrimestre::getActivitylogOptions())
 * -- se consulta directo en `activity_log`, igual que la de cobros/mora. Estos
 * tests verifican justamente eso: que la fila queda en la tabla con el causer
 * correcto, sin depender del log de archivo `actividad` que ya existía.
 */
test('cargar un trimestre deja auditado quien lo hizo', function () {
    $plantilla = PlantillaBoletin::factory()->create();
    $trimestre = BoletinTrimestre::factory()->create();
    $trimestre->boletin->update(['plantilla_id' => $plantilla->id]);
    $usuario = User::factory()->create();

    $trimestre->cargar(['promedio_anual' => 8], $usuario);

    $actividad = Activity::where('subject_type', BoletinTrimestre::class)
        ->where('subject_id', $trimestre->id)
        ->latest('id')
        ->first();

    expect($actividad)->not->toBeNull()
        ->and($actividad->causer_id)->toBe($usuario->id)
        ->and($actividad->changes()->get('attributes'))->toHaveKey('datos');
});

test('corregir un trimestre ya enviado deja auditado quien lo corrigio, aunque el metodo no lo persista en una columna', function () {
    $plantilla = PlantillaBoletin::factory()->create();
    $trimestre = BoletinTrimestre::factory()->create(['estado' => EstadoTrimestre::Enviado]);
    $trimestre->boletin->update(['plantilla_id' => $plantilla->id]);
    $usuario = User::factory()->create();

    $trimestre->corregir(['promedio_anual' => 9], $usuario);

    $actividad = Activity::where('subject_type', BoletinTrimestre::class)
        ->where('subject_id', $trimestre->id)
        ->latest('id')
        ->first();

    expect($actividad)->not->toBeNull()
        ->and($actividad->causer_id)->toBe($usuario->id);
});

/**
 * El guardado que marca `enviado` corre dentro del job encolado (en test,
 * con QUEUE_CONNECTION=sync, en el mismo proceso pero sin sesión HTTP
 * autenticada) -- sin el CauserResolver::setCauser() en
 * GenerarYEnviarBoletinPdf::handle(), este causer quedaría null.
 */
test('confirmar y enviar un trimestre deja auditado quien lo confirmo, sin sesion autenticada', function () {
    Storage::fake('local');
    Mail::fake();
    expect(auth()->user())->toBeNull();

    $plantilla = PlantillaBoletin::factory()->create(['estructura_campos' => ['secciones' => []]]);
    $boletin = Boletin::factory()->create(['plantilla_id' => $plantilla->id]);
    $trimestre = BoletinTrimestre::factory()->cargado()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);
    $usuario = User::factory()->create();

    $trimestre->confirmarYEnviar($usuario);

    $actividad = Activity::where('subject_type', BoletinTrimestre::class)
        ->where('subject_id', $trimestre->id)
        ->latest('id')
        ->first();

    expect($actividad)->not->toBeNull()
        ->and($actividad->causer_id)->toBe($usuario->id)
        ->and($trimestre->fresh()->estado)->toBe(EstadoTrimestre::Enviado);
});
