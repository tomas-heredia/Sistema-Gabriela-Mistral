<?php

use App\Alumnos\Livewire\Alumnos\Formulario;
use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Enums\Nivel;
use App\Alumnos\Models\Enums\Turno;
use App\Alumnos\Models\Tutor;
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

    Livewire::actingAs($cobrador)->test(Formulario::class)
        ->set('nombre', 'Ana Pérez')
        ->set('fecha_nacimiento', '2015-03-10')
        ->set('nivel', Nivel::Primario->value)
        ->set('grado', '4to grado')
        ->set('turno', Turno::Manana->value)
        ->call('guardar');

    $alumno = Alumno::where('nombre', 'Ana Pérez')->firstOrFail();
    expect($alumno->grado)->toBe('4to grado');
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

    $component = Livewire::actingAs($cobrador)->test(Formulario::class)
        ->set('nombre', 'Beto Gómez')
        ->set('fecha_nacimiento', '2010-03-10')
        ->set('nivel', Nivel::Secundario->value)
        ->set('anio_secundaria', 3)
        ->set('turno', Turno::Tarde->value);

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

test('quitar un tutor lo desvincula del alumno', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $alumno = Alumno::factory()->primario()->create();
    $tutor = Tutor::factory()->create();
    $alumno->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);

    Livewire::actingAs($cobrador)->test(Formulario::class, ['alumno' => $alumno])
        ->call('desvincularTutor', $tutor->id);

    expect($alumno->tutores()->count())->toBe(0);
});
