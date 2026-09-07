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
                    'columnas' => [
                        ['id' => 'espacio', 'nombre' => 'Espacio Curricular', 'tipo' => 'texto'],
                        ['id' => 'calificacion', 'nombre' => 'Calificación', 'tipo' => 'numero'],
                    ],
                    'filas_libres' => true,
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

test('profesor no puede montar el componente', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    $boletin = Boletin::factory()->create(['plantilla_id' => $this->plantilla->id]);
    $trimestre = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);

    Livewire::actingAs($profesor)->test(Cargar::class, ['boletinTrimestre' => $trimestre])->assertForbidden();
});
