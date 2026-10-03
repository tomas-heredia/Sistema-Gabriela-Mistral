<?php

use App\Alumnos\Models\Alumno;
use App\Boletines\Livewire\Boletines\Listado;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Boletines\Models\PlantillaBoletin;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('lista los boletines del periodo activo con el estado de sus trimestres', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->primario()->create(['nombre' => 'Ana Pérez']);
    $boletin = Boletin::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id]);
    BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertSee('Ana Pérez')
        ->assertSee('T1');
});

test('no muestra boletines de otro periodo', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodoActivo = PeriodoLectivo::factory()->activo()->create();
    $periodoViejo = PeriodoLectivo::factory()->create(['activo' => false]);
    $alumno = Alumno::factory()->primario()->create(['nombre' => 'Bruno Gómez']);
    Boletin::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodoViejo->id]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertDontSee('Bruno Gómez');
});

test('busca por nombre o dni del alumno', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->primario()->create(['nombre' => 'Carla Díaz', 'dni' => '30111222']);
    Boletin::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->set('busqueda', '30111222')
        ->assertSee('Carla Díaz');
});

test('profesor no puede montar el componente', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Listado::class)->assertForbidden();
});

test('administra_alumnos puede montar el componente y ver los boletines del periodo activo', function () {
    $administraAlumnos = User::factory()->create()->assignRole('administra_alumnos');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->primario()->create(['nombre' => 'Elena Ruiz']);
    Boletin::factory()->create(['alumno_id' => $alumno->id, 'periodo_lectivo_id' => $periodo->id]);

    Livewire::actingAs($administraAlumnos)->test(Listado::class)
        ->assertSee('Elena Ruiz');
});

test('la busqueda encuentra tambien a un alumno sin libreta todavia', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    PeriodoLectivo::factory()->activo()->create();
    Alumno::factory()->primario()->create(['nombre' => 'Hugo Flores', 'dni' => '40555666']);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->set('busqueda', 'Hugo Flores')
        ->assertSee('Hugo Flores')
        ->assertSee('Todavía no tiene libreta')
        ->assertSee('Crear libreta');
});

test('crearLibreta genera la libreta del alumno encontrado por busqueda', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->primario()->create(['nombre' => 'Hugo Flores']);
    PlantillaBoletin::factory()->create(['nivel' => $alumno->nivel, 'anio' => null]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->set('busqueda', 'Hugo Flores')
        ->call('crearLibreta', $alumno->id)
        ->assertSee('Libreta generada correctamente');

    expect(Boletin::where('alumno_id', $alumno->id)->where('periodo_lectivo_id', $periodo->id)->exists())->toBeTrue();
});

test('crearLibreta no hace nada para un alumno de nivel inicial', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $alumno = Alumno::factory()->inicial()->create(['nombre' => 'Ines Castro']);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->set('busqueda', 'Ines Castro')
        ->assertDontSee('Crear libreta')
        ->call('crearLibreta', $alumno->id);

    expect(Boletin::where('alumno_id', $alumno->id)->where('periodo_lectivo_id', $periodo->id)->exists())->toBeFalse();
});

test('administra_alumnos no ve el boton de crear libreta en la busqueda', function () {
    $administraAlumnos = User::factory()->create()->assignRole('administra_alumnos');
    PeriodoLectivo::factory()->activo()->create();
    Alumno::factory()->primario()->create(['nombre' => 'Lucas Medina']);

    Livewire::actingAs($administraAlumnos)->test(Listado::class)
        ->set('busqueda', 'Lucas Medina')
        ->assertSee('Lucas Medina')
        ->assertDontSee('Crear libreta');
});
