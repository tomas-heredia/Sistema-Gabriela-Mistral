<?php

use App\Alumnos\Livewire\Alumnos\Formulario;
use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Enums\Nivel;
use App\Alumnos\Models\Enums\Turno;
use App\Alumnos\Models\Tutor;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\PlantillaBoletin;
use App\Cobranzas\Models\Arancel;
use App\Cobranzas\Models\Beca;
use App\Cobranzas\Models\Cuota;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('un profesor no puede montar el formulario de alumno', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Formulario::class)->assertForbidden();
});

test('crear un alumno primario guarda y redirige a su edicion', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $tutor = Tutor::factory()->create(['dni' => '30111222']);

    Livewire::actingAs($cobrador)->test(Formulario::class)
        ->set('nombre', 'Ana Pérez')
        ->set('fecha_nacimiento', '2015-03-10')
        ->set('nivel', Nivel::Primario->value)
        ->set('grado', '4to grado')
        ->set('turno', Turno::Manana->value)
        ->set('dniTutorBuscado', '30111222')
        ->call('buscarTutor')
        ->call('vincularTutor')
        ->call('guardar');

    $alumno = Alumno::where('nombre', 'Ana Pérez')->firstOrFail();
    expect($alumno->grado)->toBe('4to grado')
        ->and($alumno->tutores()->where('tutores.id', $tutor->id)->exists())->toBeTrue();
});

test('crear un alumno secundario exige el anio de secundaria', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    Livewire::actingAs($cobrador)->test(Formulario::class)
        ->set('nombre', 'Beto Gómez')
        ->set('fecha_nacimiento', '2010-03-10')
        ->set('nivel', Nivel::Secundario->value)
        ->set('turno', Turno::Tarde->value)
        ->call('guardar')
        ->assertHasErrors(['anio_secundaria']);
});

test('elegir el anio de secundaria completa el grado solo, sin campo de texto libre', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    Tutor::factory()->create(['dni' => '30111222']);

    $component = Livewire::actingAs($cobrador)->test(Formulario::class)
        ->set('nombre', 'Beto Gómez')
        ->set('fecha_nacimiento', '2010-03-10')
        ->set('nivel', Nivel::Secundario->value)
        ->set('anio_secundaria', 3)
        ->set('turno', Turno::Tarde->value)
        ->set('dniTutorBuscado', '30111222')
        ->call('buscarTutor')
        ->call('vincularTutor');

    expect($component->get('grado'))->toBe('3º año');

    $component->call('guardar');

    $alumno = Alumno::where('nombre', 'Beto Gómez')->firstOrFail();
    expect($alumno->anio_secundaria)->toBe(3)
        ->and($alumno->grado)->toBe('3º año');
});

test('rechaza un grado de primaria que no esta en la lista permitida', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    Livewire::actingAs($cobrador)->test(Formulario::class)
        ->set('nombre', 'Ana Pérez')
        ->set('fecha_nacimiento', '2015-03-10')
        ->set('nivel', Nivel::Primario->value)
        ->set('grado', '7mo grado')
        ->set('turno', Turno::Manana->value)
        ->call('guardar')
        ->assertHasErrors(['grado']);
});

test('editar un alumno precarga sus datos', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $alumno = Alumno::factory()->primario()->create(['nombre' => 'Ana Pérez']);

    Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->assertSet('nombre', 'Ana Pérez');
});

test('vincular un tutor existente lo agrega con su vinculo y responsable_pago', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $alumno = Alumno::factory()->primario()->create();
    $tutor = Tutor::factory()->create(['dni' => '30111222']);

    Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->set('dniTutorBuscado', '30111222')
        ->call('buscarTutor')
        ->assertSet('tutorEncontrado.id', $tutor->id)
        ->set('vinculoNuevo', 'madre')
        ->set('responsablePagoNuevo', true)
        ->call('vincularTutor');

    $vinculo = $alumno->tutores()->first();
    expect($vinculo)->not->toBeNull()
        ->and($vinculo->pivot->vinculo)->toBe('madre')
        ->and((bool) $vinculo->pivot->responsable_pago)->toBeTrue();
});

test('quitar un tutor lo desvincula del alumno si le queda otro', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $alumno = Alumno::factory()->primario()->create();
    $tutor1 = Tutor::factory()->create();
    $tutor2 = Tutor::factory()->create();
    $alumno->tutores()->attach($tutor1, ['vinculo' => 'madre', 'responsable_pago' => true]);
    $alumno->tutores()->attach($tutor2, ['vinculo' => 'padre', 'responsable_pago' => false]);

    Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->call('desvincularTutor', $tutor1->id);

    expect($alumno->tutores()->count())->toBe(1)
        ->and($alumno->tutores()->first()->id)->toBe($tutor2->id);
});

test('no se puede quitar el ultimo tutor de un alumno', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $alumno = Alumno::factory()->primario()->create();
    $tutor = Tutor::factory()->create();
    $alumno->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);

    Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->call('desvincularTutor', $tutor->id)
        ->assertOk();

    expect($alumno->tutores()->count())->toBe(1);
});

test('sin vincular ningun tutor no se puede crear el alumno', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    Livewire::actingAs($cobrador)->test(Formulario::class)
        ->set('nombre', 'Ana Pérez')
        ->set('fecha_nacimiento', '2015-03-10')
        ->set('nivel', Nivel::Primario->value)
        ->set('grado', '4to grado')
        ->set('turno', Turno::Manana->value)
        ->call('guardar')
        ->assertHasErrors(['tutoresPendientes']);

    expect(Alumno::where('nombre', 'Ana Pérez')->exists())->toBeFalse();
});

test('vincular un tutor en el alta lo agrega a la lista pendiente sin crear el alumno todavia', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $tutor = Tutor::factory()->create(['dni' => '30111222']);

    $component = Livewire::actingAs($cobrador)->test(Formulario::class)
        ->set('dniTutorBuscado', '30111222')
        ->call('buscarTutor')
        ->call('vincularTutor');

    expect($component->get('tutoresPendientes'))->toHaveCount(1)
        ->and($component->get('tutoresPendientes')[0]['tutor_id'])->toBe($tutor->id)
        ->and(Alumno::count())->toBe(0);
});

test('no se puede agregar el mismo tutor dos veces a la lista pendiente', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    Tutor::factory()->create(['dni' => '30111222']);

    Livewire::actingAs($cobrador)->test(Formulario::class)
        ->set('dniTutorBuscado', '30111222')
        ->call('buscarTutor')
        ->call('vincularTutor')
        ->set('dniTutorBuscado', '30111222')
        ->call('buscarTutor')
        ->call('vincularTutor')
        ->assertHasErrors(['dniTutorBuscado']);
});

test('buscar un dni sin resultado ofrece crear un tutor nuevo', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    Livewire::actingAs($cobrador)->test(Formulario::class)
        ->set('dniTutorBuscado', '30999888')
        ->call('buscarTutor')
        ->assertSet('tutorEncontrado', null)
        ->assertSee('Crear uno nuevo');
});

test('crear un tutor nuevo en el alta lo agrega a la lista pendiente con el dni ya precargado', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $component = Livewire::actingAs($cobrador)->test(Formulario::class)
        ->set('dniTutorBuscado', '30999888')
        ->call('buscarTutor')
        ->call('mostrarCrearTutor')
        ->assertSet('dniTutorACrear', '30999888')
        ->set('nombreTutorACrear', 'Marta Gómez')
        ->set('domicilioTutorACrear', 'San Martín 123')
        ->set('telefonoTutorACrear', '3834111222')
        ->set('correoTutorACrear', 'marta@example.com')
        ->set('vinculoNuevo', 'madre')
        ->set('responsablePagoNuevo', true)
        ->call('crearYVincularTutor');

    $tutor = Tutor::where('dni', '30999888')->firstOrFail();
    expect($tutor->nombre)->toBe('Marta Gómez')
        ->and($tutor->correo)->toBe('marta@example.com')
        ->and($component->get('tutoresPendientes'))->toHaveCount(1)
        ->and($component->get('tutoresPendientes')[0]['tutor_id'])->toBe($tutor->id)
        ->and($component->get('creandoTutorNuevo'))->toBeFalse();
});

test('crear un tutor nuevo al editar un alumno lo vincula directo', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $alumno = Alumno::factory()->primario()->create();

    Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->set('dniTutorBuscado', '30999888')
        ->call('buscarTutor')
        ->call('mostrarCrearTutor')
        ->set('nombreTutorACrear', 'Marta Gómez')
        ->set('dniTutorACrear', '30999888')
        ->set('domicilioTutorACrear', 'San Martín 123')
        ->set('telefonoTutorACrear', '3834111222')
        ->set('correoTutorACrear', 'marta@example.com')
        ->call('crearYVincularTutor');

    $tutor = Tutor::where('dni', '30999888')->firstOrFail();
    expect($alumno->tutores()->where('tutores.id', $tutor->id)->exists())->toBeTrue();
});

test('crear un tutor nuevo exige los mismos campos que su propia pantalla', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    Livewire::actingAs($cobrador)->test(Formulario::class)
        ->call('mostrarCrearTutor')
        ->call('crearYVincularTutor')
        ->assertHasErrors([
            'nombreTutorACrear' => 'required',
            'dniTutorACrear' => 'required',
            'domicilioTutorACrear' => 'required',
            'telefonoTutorACrear' => 'required',
            'correoTutorACrear' => 'required',
        ]);

    expect(Tutor::count())->toBe(0);
});

test('crear un tutor nuevo con un dni ya usado rechaza la duplicacion', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    Tutor::factory()->create(['dni' => '30999888']);

    Livewire::actingAs($cobrador)->test(Formulario::class)
        ->call('mostrarCrearTutor')
        ->set('nombreTutorACrear', 'Marta Gómez')
        ->set('dniTutorACrear', '30999888')
        ->set('domicilioTutorACrear', 'San Martín 123')
        ->set('telefonoTutorACrear', '3834111222')
        ->set('correoTutorACrear', 'marta@example.com')
        ->call('crearYVincularTutor')
        ->assertHasErrors(['dniTutorACrear' => 'unique']);

    expect(Tutor::where('dni', '30999888')->count())->toBe(1);
});

test('generar cuotas crea las cuotas del periodo activo y ya no ofrece el boton', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create([
        'fecha_inicio' => '2026-03-01',
        'fecha_fin' => '2026-12-15',
    ]);
    $alumno = Alumno::factory()->primario()->create();
    Arancel::factory()->matricula()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno->nivel]);
    Arancel::factory()->mensualidad()->create(['periodo_lectivo_id' => $periodo->id, 'nivel' => $alumno->nivel]);

    $component = Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->assertSee('Generar cuotas del período')
        ->call('generarCuotas');

    expect(Cuota::where('alumno_id', $alumno->id)->count())->toBe(11);

    $component->assertDontSee('Generar cuotas del período');
});

test('generar cuotas sin arancel cargado muestra un mensaje claro, no un error', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->primario()->create();

    // Sin arancel cargado, generarCuotas() no debe tirar un error 500 ni
    // dejar cuotas a medio crear.
    Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->call('generarCuotas')
        ->assertOk();

    expect(Cuota::where('alumno_id', $alumno->id)->count())->toBe(0);
});

test('generar boletin crea el boletin con sus 3 trimestres pendientes y ya no ofrece el boton', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->primario()->create();
    PlantillaBoletin::factory()->create(['nivel' => $alumno->nivel, 'anio' => null]);

    $component = Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->assertSee('Generar libreta del período')
        ->call('generarBoletin');

    $boletin = Boletin::where('alumno_id', $alumno->id)->where('periodo_lectivo_id', $periodo->id)->first();

    expect($boletin)->not->toBeNull()
        ->and(BoletinTrimestre::where('boletin_id', $boletin->id)->count())->toBe(3);

    $component->assertDontSee('Generar libreta del período');
});

test('generar boletin sin plantilla activa muestra un mensaje claro, no un error', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->primario()->create();

    // Sin plantilla activa para el nivel/año, generarBoletin() no debe
    // tirar un error 500 ni dejar un boletín a medio crear.
    Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->call('generarBoletin')
        ->assertOk();

    expect(Boletin::where('alumno_id', $alumno->id)->count())->toBe(0);
});

test('otorgar una beca crea el registro con motivo y usuario que la aprueba', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->primario()->create();

    Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->set('becado', true)
        ->set('motivoBeca', 'Beca socioeconómica')
        ->call('guardarBeca');

    $beca = Beca::where('alumno_id', $alumno->id)->where('periodo_lectivo_id', $periodo->id)->first();
    expect($beca)->not->toBeNull()
        ->and($beca->motivo)->toBe('Beca socioeconómica')
        ->and($beca->aprobado_por_id)->toBe($cobrador->id)
        ->and($beca->fecha_otorgamiento->isToday())->toBeTrue();
});

test('el motivo es obligatorio para otorgar una beca', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->primario()->create();

    Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->set('becado', true)
        ->set('motivoBeca', '')
        ->call('guardarBeca')
        ->assertHasErrors(['motivoBeca' => 'required']);

    expect(Beca::where('alumno_id', $alumno->id)->exists())->toBeFalse();
});

test('revocar una beca existente la elimina', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->primario()->create();
    Beca::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id]);

    Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->assertSet('becado', true)
        ->set('becado', false)
        ->call('guardarBeca');

    expect(Beca::where('alumno_id', $alumno->id)->exists())->toBeFalse();
});

test('volver a otorgar despues de revocar actualiza el motivo en vez de duplicar', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->primario()->create();
    Beca::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id, 'motivo' => 'Beca deportiva']);

    Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->set('motivoBeca', 'Beca convenio institucional')
        ->call('guardarBeca');

    expect(Beca::where('alumno_id', $alumno->id)->count())->toBe(1)
        ->and(Beca::where('alumno_id', $alumno->id)->first()->motivo)->toBe('Beca convenio institucional');
});

test('agregar etapa de apoyo crea el trimestre 4 para un alumno primario', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->primario()->create();
    PlantillaBoletin::factory()->create(['nivel' => $alumno->nivel, 'anio' => null]);
    $boletin = Boletin::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id]);
    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);
    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 2]);
    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 3]);

    Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->assertSee('Agregar Etapa de Apoyo')
        ->call('agregarEtapaApoyo');

    expect(BoletinTrimestre::where('boletin_id', $boletin->id)->where('trimestre', 4)->exists())->toBeTrue();
});

test('agregar etapa de apoyo no aparece ni hace nada para un alumno secundario', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->secundario()->create();
    PlantillaBoletin::factory()->secundaria($alumno->anio_secundaria)->create();
    $boletin = Boletin::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id]);
    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);

    Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->assertDontSee('Agregar Etapa de Apoyo')
        ->call('agregarEtapaApoyo');

    expect(BoletinTrimestre::where('boletin_id', $boletin->id)->where('trimestre', 4)->exists())->toBeFalse();
});

test('agregar etapa de apoyo no duplica si ya existe', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->primario()->create();
    PlantillaBoletin::factory()->create(['nivel' => $alumno->nivel, 'anio' => null]);
    $boletin = Boletin::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id]);
    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 4]);

    Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->assertDontSee('Agregar Etapa de Apoyo')
        ->call('agregarEtapaApoyo');

    expect(BoletinTrimestre::where('boletin_id', $boletin->id)->where('trimestre', 4)->count())->toBe(1);
});
