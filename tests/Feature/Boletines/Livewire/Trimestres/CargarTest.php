<?php

use App\Boletines\Jobs\GenerarYEnviarBoletinPdf;
use App\Boletines\Livewire\Trimestres\Cargar;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\Enums\EstadoTrimestre;
use App\Boletines\Models\PlantillaBoletin;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/**
 * Plantilla con la forma real de secundaria post-ICAIP: trimestre_1/2/3
 * en sus propios momentos, e ICAIP Diciembre/Febrero/Calificación Final
 * como columnas comunes del 3er trimestre (ver database/seeders/data/
 * plantillas_boletin/secundaria_1.json), más Espacios Pendientes de
 * Acreditación para los tests de persistencia entre trimestres.
 */
function plantillaConIcaip(): PlantillaBoletin
{
    return PlantillaBoletin::factory()->create([
        'estructura_campos' => [
            'secciones' => [
                [
                    'id' => 'espacios_curriculares',
                    'titulo' => 'Espacios Curriculares',
                    'tipo' => 'tabla_materias',
                    'escala' => ['tipo' => 'numerica', 'min' => 1, 'max' => 10],
                    'columnas' => [
                        ['id' => 'trimestre_1', 'nombre' => 'Trimestre 1°', 'momento' => 'trimestre_1'],
                        ['id' => 'trimestre_2', 'nombre' => 'Trimestre 2°', 'momento' => 'trimestre_2'],
                        ['id' => 'trimestre_3', 'nombre' => 'Trimestre 3°', 'momento' => 'trimestre_3'],
                        ['id' => 'icaip_diciembre', 'nombre' => 'ICAIP Diciembre', 'momento' => 'trimestre_3'],
                        ['id' => 'icaip_febrero', 'nombre' => 'ICAIP Febrero', 'momento' => 'trimestre_3'],
                        ['id' => 'calificacion_final', 'nombre' => 'Calificación Final', 'momento' => 'trimestre_3', 'acepta_coma' => true],
                    ],
                    'filas' => [
                        ['id' => 'lengua', 'nombre' => 'Lengua'],
                        ['id' => 'matematica', 'nombre' => 'Matemática'],
                    ],
                ],
                [
                    'id' => 'espacios_pendientes_acreditacion',
                    'titulo' => 'Espacios Pendientes de Acreditación',
                    'tipo' => 'tabla_materias_libre',
                    'etiqueta_nombre' => 'Número de Orden',
                    'tipo_nombre' => 'numero',
                    'columnas' => [
                        ['id' => 'espacio_curricular', 'nombre' => 'Espacio Curricular', 'tipo' => 'texto'],
                        ['id' => 'calificacion', 'nombre' => 'Calificación', 'tipo' => 'numero'],
                    ],
                    'filas_libres' => true,
                ],
            ],
        ],
    ]);
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->plantilla = PlantillaBoletin::factory()->create([
        'estructura_campos' => [
            'secciones' => [
                [
                    'id' => 'espacios_curriculares',
                    'titulo' => 'Espacios Curriculares',
                    'tipo' => 'tabla_materias',
                    'escala' => ['tipo' => 'numerica', 'min' => 1, 'max' => 10],
                    'columnas' => [
                        ['id' => 'trimestre_1', 'nombre' => 'Trimestre 1', 'momento' => 'trimestre_1'],
                        ['id' => 'trimestre_3', 'nombre' => 'Trimestre 3', 'momento' => 'trimestre_3'],
                        ['id' => 'calificacion_final', 'nombre' => 'Calificación Final', 'momento' => 'trimestre_3'],
                        ['id' => 'nota_diciembre', 'nombre' => 'Nota Diciembre', 'momento' => 'trimestre_4'],
                        ['id' => 'nota_final', 'nombre' => 'Nota Final', 'momento' => 'trimestre_6'],
                    ],
                    'filas' => [
                        ['id' => 'lengua', 'nombre' => 'Lengua'],
                        ['id' => 'matematica', 'nombre' => 'Matemática'],
                    ],
                ],
                [
                    'id' => 'promocion',
                    'titulo' => 'Promoción',
                    'tipo' => 'campo_seleccion',
                    'momento' => 'trimestre_3',
                    'opciones' => ['Promocionado', 'No promocionado'],
                ],
                [
                    'id' => 'asistencia',
                    'titulo' => 'Asistencia',
                    'tipo' => 'tabla_metricas',
                    'escala' => ['tipo' => 'numerica'],
                    'columnas' => [
                        ['id' => 'trimestre_1', 'nombre' => 'Trimestre 1', 'momento' => 'trimestre_1'],
                    ],
                    'filas' => [
                        ['id' => 'inasistencias', 'nombre' => 'Inasistencias'],
                    ],
                ],
                [
                    'id' => 'apreciaciones',
                    'titulo' => 'Apreciaciones',
                    'tipo' => 'tabla_criterios',
                    'escala' => ['tipo' => 'texto', 'opciones' => ['S', 'AV', 'PV', 'N'], 'leyenda' => 'Siempre / A veces / Pocas veces / Nunca'],
                    'columnas' => [
                        ['id' => 'trimestre_1', 'nombre' => 'Trimestre 1', 'momento' => 'trimestre_1'],
                    ],
                    'filas' => [
                        ['id' => 'participa', 'nombre' => 'Participa en clase'],
                    ],
                ],
                [
                    'id' => 'espacios_pendientes',
                    'titulo' => 'Espacios Pendientes de Acreditación',
                    'tipo' => 'tabla_materias_libre',
                    'etiqueta_nombre' => 'Número de Orden',
                    'tipo_nombre' => 'numero',
                    'columnas' => [
                        ['id' => 'espacio', 'nombre' => 'Espacio Curricular', 'tipo' => 'texto'],
                        ['id' => 'calificacion', 'nombre' => 'Calificación', 'tipo' => 'numero'],
                    ],
                    'filas_libres' => true,
                ],
                [
                    'id' => 'promedio_anual',
                    'titulo' => 'Promedio Anual',
                    'tipo' => 'campo_numero',
                    'momento' => 'trimestre_3',
                    'acepta_coma' => true,
                ],
                [
                    'id' => 'promedio_materias',
                    'titulo' => 'Promedio de Materias',
                    'tipo' => 'campo_numero',
                    'momento' => 'trimestre_6',
                    'acepta_coma' => true,
                ],
            ],
        ],
    ]);
});

test('solo muestra las columnas y secciones del trimestre que se esta cargando', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre1 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre1])
        ->assertSee('Trimestre 1')
        ->assertDontSee('Calificación Final')
        ->assertDontSee('Promoción');
});

test('el trimestre 3 pide las columnas y secciones que aplican en ese momento', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre3 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 3]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre3])
        ->assertSee('Calificación Final')
        ->assertSee('Promoción');
});

test('guardar borrador persiste los datos y pasa a cargado', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre1 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre1])
        ->set('datos.espacios_curriculares.0.trimestre_1', '8')
        ->call('guardarBorrador');

    $trimestre1->refresh();

    expect($trimestre1->estado)->toBe(EstadoTrimestre::Cargado)
        ->and($trimestre1->cargado_por_id)->toBe($cobrador->id)
        ->and($trimestre1->datos['espacios_curriculares'][0]['trimestre_1'])->toBe('8');
});

test('confirmar y enviar dispatcha el job solo si el trimestre esta cargado', function () {
    Queue::fake();

    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => 3,
        'estado' => EstadoTrimestre::Cargado,
    ]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre])
        ->call('confirmarYEnviar')
        ->assertRedirect(route('boletines.index'));

    Queue::assertPushed(GenerarYEnviarBoletinPdf::class);
});

test('confirmar y enviar rechaza un trimestre que todavia no esta cargado', function () {
    Queue::fake();

    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre])
        ->call('confirmarYEnviar')
        ->assertOk();

    Queue::assertNotPushed(GenerarYEnviarBoletinPdf::class);
    expect($trimestre->refresh()->estado)->toBe(EstadoTrimestre::Pendiente);
});

test('un trimestre enviado se muestra de solo lectura sin los botones de accion', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => 1,
        'estado' => EstadoTrimestre::Enviado,
    ]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre])
        ->assertDontSee('Guardar borrador')
        ->assertDontSee('Confirmar y enviar');
});

test('una nota fuera de 0 a 10 se ajusta al limite mas cercano al guardar', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre1 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre1])
        ->set('datos.espacios_curriculares.0.trimestre_1', '15')
        ->set('datos.espacios_curriculares.1.trimestre_1', '-3')
        ->call('guardarBorrador');

    $trimestre1->refresh();

    expect($trimestre1->datos['espacios_curriculares'][0]['trimestre_1'])->toBe('10')
        ->and($trimestre1->datos['espacios_curriculares'][1]['trimestre_1'])->toBe('0');
});

test('un campo numerico simple (sin ser parte de una grilla) tambien se ajusta de 0 a 10 al guardar', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre3 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 3]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre3])
        ->set('datos.promedio_anual', '12')
        ->call('guardarBorrador');

    expect($trimestre3->refresh()->datos['promedio_anual'])->toBe('10');
});

test('la columna libre de una tabla usa la etiqueta y el tipo de input configurados en la seccion', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre1 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre1])
        ->call('agregarFila', 'espacios_pendientes')
        ->assertSee('Número de Orden')
        ->assertSeeHtml('type="number"', false)
        ->assertSeeHtml('wire:model="datos.espacios_pendientes.0.nombre"', false);
});

test('la asistencia no permite valores negativos pero no tiene techo', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre1 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre1])
        ->set('datos.asistencia.0.trimestre_1', '-5')
        ->call('guardarBorrador');

    $trimestre1->refresh();

    expect($trimestre1->datos['asistencia'][0]['trimestre_1'])->toBe('0');

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre1])
        ->set('datos.asistencia.0.trimestre_1', '180')
        ->call('guardarBorrador');

    expect($trimestre1->refresh()->datos['asistencia'][0]['trimestre_1'])->toBe('180');
});

test('una columna sin momento se muestra en cualquier trimestre', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre1 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);
    $trimestre2 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 2]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre1])
        ->assertSee('Espacios Pendientes de Acreditación');

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre2])
        ->assertSee('Espacios Pendientes de Acreditación');
});

test('las opciones de una escala muestran su significado completo', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre1 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre1])
        ->assertSee('S — Siempre')
        ->assertSee('AV — A veces')
        ->assertSee('N — Nunca');
});

test('profesor sin permiso de cargar boletines no puede montar el componente', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);

    Livewire::actingAs($profesor)->test(Cargar::class, ['boletinTrimestre' => $trimestre])->assertForbidden();
});

test('un administrador puede editar y guardar la correccion de un trimestre ya enviado', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => 1,
        'estado' => EstadoTrimestre::Enviado,
        'datos' => ['espacios_curriculares' => [['nombre' => 'Lengua', 'trimestre_1' => '7'], ['nombre' => 'Matemática', 'trimestre_1' => '6']]],
    ]);

    Livewire::actingAs($administrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre])
        ->assertSee('Guardar corrección')
        ->set('datos.espacios_curriculares.0.trimestre_1', '9')
        ->call('guardarCorreccion');

    $trimestre->refresh();

    expect($trimestre->estado)->toBe(EstadoTrimestre::Enviado)
        ->and($trimestre->datos['espacios_curriculares'][0]['trimestre_1'])->toBe('9')
        ->and($trimestre->requiere_reenvio)->toBeTrue();
});

test('un cobrador no puede editar un trimestre ya enviado, solo un administrador puede', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => 1,
        'estado' => EstadoTrimestre::Enviado,
    ]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre])
        ->assertDontSee('Guardar corrección')
        ->call('guardarCorreccion')
        ->assertForbidden();
});

test('reenviar la correccion despacha el job solo cuando hay una pendiente', function () {
    Queue::fake();

    $administrador = User::factory()->create()->assignRole('administrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => 1,
        'estado' => EstadoTrimestre::Enviado,
        'requiere_reenvio' => true,
    ]);

    Livewire::actingAs($administrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre])
        ->assertSee('Reenviar corrección')
        ->call('reenviarCorreccion');

    Queue::assertPushed(GenerarYEnviarBoletinPdf::class, fn ($job) => $job->trimestre->is($trimestre) && $job->esCorreccion === true);
});

test('un cobrador no puede reenviar una correccion', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => 1,
        'estado' => EstadoTrimestre::Enviado,
        'requiere_reenvio' => true,
    ]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre])
        ->call('reenviarCorreccion')
        ->assertForbidden();
});

test('director_secretario puede cargar un borrador y confirmar y enviar el trimestre sin ningun permiso extra', function () {
    Queue::fake();

    $directorSecretario = User::factory()->create()->assignRole('director_secretario');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre1 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);

    Livewire::actingAs($directorSecretario)->test(Cargar::class, ['boletinTrimestre' => $trimestre1])
        ->set('datos.espacios_curriculares.0.trimestre_1', '9')
        ->call('guardarBorrador');

    expect($trimestre1->refresh()->estado)->toBe(EstadoTrimestre::Cargado)
        ->and($trimestre1->cargado_por_id)->toBe($directorSecretario->id);

    Livewire::actingAs($directorSecretario)->test(Cargar::class, ['boletinTrimestre' => $trimestre1])
        ->call('confirmarYEnviar')
        ->assertRedirect(route('boletines.index'));

    Queue::assertPushed(GenerarYEnviarBoletinPdf::class);
});

test('director_secretario puede corregir y reenviar un trimestre ya enviado, igual que administrador', function () {
    Queue::fake();

    $directorSecretario = User::factory()->create()->assignRole('director_secretario');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => 1,
        'estado' => EstadoTrimestre::Enviado,
        'datos' => ['espacios_curriculares' => [['nombre' => 'Lengua', 'trimestre_1' => '7'], ['nombre' => 'Matemática', 'trimestre_1' => '6']]],
    ]);

    Livewire::actingAs($directorSecretario)->test(Cargar::class, ['boletinTrimestre' => $trimestre])
        ->assertSee('Guardar corrección')
        ->set('datos.espacios_curriculares.0.trimestre_1', '9')
        ->call('guardarCorreccion');

    $trimestre->refresh();

    expect($trimestre->estado)->toBe(EstadoTrimestre::Enviado)
        ->and($trimestre->datos['espacios_curriculares'][0]['trimestre_1'])->toBe('9')
        ->and($trimestre->requiere_reenvio)->toBeTrue();

    Livewire::actingAs($directorSecretario)->test(Cargar::class, ['boletinTrimestre' => $trimestre])
        ->assertSee('Reenviar corrección')
        ->call('reenviarCorreccion');

    Queue::assertPushed(GenerarYEnviarBoletinPdf::class, fn ($job) => $job->trimestre->is($trimestre) && $job->esCorreccion === true);
});

test('profesor con permiso de cargar boletines puede cargar un borrador y confirmar y enviar el trimestre', function () {
    Queue::fake();

    $profesor = User::factory()->create()->assignRole('profesor');
    $profesor->givePermissionTo('cargar_boletines');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre1 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);

    Livewire::actingAs($profesor)->test(Cargar::class, ['boletinTrimestre' => $trimestre1])
        ->set('datos.espacios_curriculares.0.trimestre_1', '9')
        ->call('guardarBorrador');

    expect($trimestre1->refresh()->estado)->toBe(EstadoTrimestre::Cargado)
        ->and($trimestre1->cargado_por_id)->toBe($profesor->id);

    Livewire::actingAs($profesor)->test(Cargar::class, ['boletinTrimestre' => $trimestre1])
        ->call('confirmarYEnviar')
        ->assertRedirect(route('boletines.index'));

    Queue::assertPushed(GenerarYEnviarBoletinPdf::class);
});

test('promedio de materias es un campo manual editable, nunca se calcula solo', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestreFinal = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => BoletinTrimestre::TRIMESTRE_LIBRETA_FINAL,
        'datos' => ['espacios_curriculares' => [
            ['nombre' => 'Lengua', 'nota_final' => '8'],
            ['nombre' => 'Matemática', 'nota_final' => '10'],
        ]],
    ]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestreFinal])
        ->assertSee('Promedio de Materias')
        ->assertSeeHtml('wire:model="datos.promedio_materias"', false)
        // Acepta coma decimal (ver clamparNumero()) -- se guarda con punto.
        ->set('datos.promedio_materias', '8,3')
        ->call('guardarBorrador');

    expect($trimestreFinal->refresh()->datos['promedio_materias'])->toBe('8.3');
});

test('la libreta final llega con la nota final precargada y el cobrador puede corregirla antes de enviar', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestreFinal = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => BoletinTrimestre::TRIMESTRE_LIBRETA_FINAL,
        'datos' => ['espacios_curriculares' => [
            ['nombre' => 'Lengua', 'nota_final' => '7'],
            ['nombre' => 'Matemática', 'nota_final' => '8'],
        ]],
    ]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestreFinal])
        ->assertSet('datos.espacios_curriculares.0.nota_final', '7')
        ->set('datos.espacios_curriculares.0.nota_final', '9')
        ->call('guardarBorrador');

    expect($trimestreFinal->refresh()->datos['espacios_curriculares'][0]['nota_final'])->toBe('9');
});

test('una recuperacion filtrada a solo las materias desaprobadas se carga, guarda y envia como cualquier trimestre', function () {
    Queue::fake();

    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $diciembre = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => BoletinTrimestre::TRIMESTRE_RECUPERACION_DICIEMBRE,
        'datos' => ['espacios_curriculares' => [['nombre' => 'Matemática', 'nota_diciembre' => '']]],
    ]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $diciembre])
        ->assertSee('Recuperación Diciembre')
        ->assertDontSee('Lengua')
        ->assertSee('Matemática')
        ->set('datos.espacios_curriculares.0.nota_diciembre', '7')
        ->call('guardarBorrador');

    expect($diciembre->refresh()->estado)->toBe(EstadoTrimestre::Cargado)
        ->and($diciembre->datos['espacios_curriculares'])->toBe([['nombre' => 'Matemática', 'nota_diciembre' => '7']]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $diciembre])
        ->call('confirmarYEnviar');

    Queue::assertPushed(GenerarYEnviarBoletinPdf::class);
});

test('ICAIP Diciembre, ICAIP Febrero y Calificacion Final solo se muestran en la pantalla del 3er trimestre', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => plantillaConIcaip()->id]);
    $trimestre1 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);
    $trimestre3 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 3]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre1])
        ->assertDontSee('ICAIP Diciembre')
        ->assertDontSee('ICAIP Febrero')
        ->assertDontSee('Calificación Final');

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre3])
        ->assertSee('ICAIP Diciembre')
        ->assertSee('ICAIP Febrero')
        ->assertSee('Calificación Final');
});

test('cualquier fila de Espacios Curriculares puede completar ICAIP, sin que el sistema decida cuales lo necesitan', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => plantillaConIcaip()->id]);
    $trimestre3 = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => 3,
        'datos' => ['espacios_curriculares' => [
            ['nombre' => 'Lengua', 'trimestre_3' => '9', 'icaip_diciembre' => '', 'icaip_febrero' => '', 'calificacion_final' => ''],
            ['nombre' => 'Matemática', 'trimestre_3' => '8', 'icaip_diciembre' => '', 'icaip_febrero' => '', 'calificacion_final' => ''],
        ]],
    ]);

    // "Lengua" aprobó el 3er trimestre igual -- se le completa ICAIP a mano.
    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre3])
        ->set('datos.espacios_curriculares.0.icaip_diciembre', '7')
        ->call('guardarBorrador');

    expect($trimestre3->refresh()->datos['espacios_curriculares'][0]['icaip_diciembre'])->toBe('7');
});

test('Calificacion Final es un campo manual, no se autocompleta al cargar ICAIP', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => plantillaConIcaip()->id]);
    $trimestre3 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 3]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre3])
        ->set('datos.espacios_curriculares.0.icaip_febrero', '7')
        ->assertSet('datos.espacios_curriculares.0.calificacion_final', '');
});

test('Calificacion Final acepta coma decimal y se guarda con punto', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => plantillaConIcaip()->id]);
    $trimestre3 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 3]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre3])
        ->set('datos.espacios_curriculares.0.calificacion_final', '7,7')
        ->call('guardarBorrador');

    expect($trimestre3->refresh()->datos['espacios_curriculares'][0]['calificacion_final'])->toBe('7.7');
});

test('lo cargado en Espacios Pendientes de Acreditacion en un trimestre se mantiene como punto de partida en el siguiente', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => plantillaConIcaip()->id]);
    $trimestre1 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);
    $trimestre2 = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 2]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre1])
        ->call('agregarFila', 'espacios_pendientes_acreditacion')
        ->set('datos.espacios_pendientes_acreditacion.0.nombre', '1')
        ->set('datos.espacios_pendientes_acreditacion.0.espacio_curricular', 'Matemática')
        ->call('guardarBorrador');

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre2])
        ->assertSet('datos.espacios_pendientes_acreditacion.0.espacio_curricular', 'Matemática');
});

test('un trimestre con Espacios Pendientes de Acreditacion guardado vacio a proposito no se pisa con lo del trimestre anterior', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $boletin = Boletin::factory()->create(['plantilla_id' => plantillaConIcaip()->id]);
    BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => 1,
        'datos' => ['espacios_pendientes_acreditacion' => [['nombre' => '1', 'espacio_curricular' => 'Matemática']]],
    ]);
    $trimestre2 = BoletinTrimestre::factory()->create([
        'boletin_id' => $boletin->id,
        'trimestre' => 2,
        'datos' => ['espacios_pendientes_acreditacion' => []],
    ]);

    Livewire::actingAs($cobrador)->test(Cargar::class, ['boletinTrimestre' => $trimestre2])
        ->assertSet('datos.espacios_pendientes_acreditacion', []);
});
