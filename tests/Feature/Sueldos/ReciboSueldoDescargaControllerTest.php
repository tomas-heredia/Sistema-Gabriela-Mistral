<?php

use App\Core\Models\User;
use App\Sueldos\Models\ReciboSueldo;
use App\Sueldos\Models\ReciboSueldoArchivo;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');
});

test('un profesor puede descargar su propio recibo', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    Storage::disk('local')->put('recibos-sueldo/1/2026-03/a.pdf', 'contenido');
    $recibo = ReciboSueldo::factory()->create(['profesor_id' => $profesor->id]);
    $archivo = ReciboSueldoArchivo::factory()->create(['recibo_sueldo_id' => $recibo->id, 'archivo' => 'recibos-sueldo/1/2026-03/a.pdf']);

    $this->actingAs($profesor)->get(route('recibos-sueldo.descargar', $archivo))->assertOk();
});

test('un profesor no puede descargar el recibo de otro', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    $otroProfesor = User::factory()->create()->assignRole('profesor');
    Storage::disk('local')->put('recibos-sueldo/2/2026-03/a.pdf', 'contenido');
    $recibo = ReciboSueldo::factory()->create(['profesor_id' => $otroProfesor->id]);
    $archivo = ReciboSueldoArchivo::factory()->create(['recibo_sueldo_id' => $recibo->id, 'archivo' => 'recibos-sueldo/2/2026-03/a.pdf']);

    $this->actingAs($profesor)->get(route('recibos-sueldo.descargar', $archivo))->assertForbidden();
});

test('un administrador puede descargar cualquier recibo', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    Storage::disk('local')->put('recibos-sueldo/3/2026-03/a.pdf', 'contenido');
    $recibo = ReciboSueldo::factory()->create();
    $archivo = ReciboSueldoArchivo::factory()->create(['recibo_sueldo_id' => $recibo->id, 'archivo' => 'recibos-sueldo/3/2026-03/a.pdf']);

    $this->actingAs($administrador)->get(route('recibos-sueldo.descargar', $archivo))->assertOk();
});

test('un administrador puede descargar cada archivo de un recibo con varios', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    Storage::disk('local')->put('recibos-sueldo/5/2026-03/a.pdf', 'contenido a');
    Storage::disk('local')->put('recibos-sueldo/5/2026-03/b.pdf', 'contenido b');
    $recibo = ReciboSueldo::factory()->create();
    $archivoA = ReciboSueldoArchivo::factory()->create(['recibo_sueldo_id' => $recibo->id, 'archivo' => 'recibos-sueldo/5/2026-03/a.pdf']);
    $archivoB = ReciboSueldoArchivo::factory()->create(['recibo_sueldo_id' => $recibo->id, 'archivo' => 'recibos-sueldo/5/2026-03/b.pdf']);

    $this->actingAs($administrador)->get(route('recibos-sueldo.descargar', $archivoA))->assertOk();
    $this->actingAs($administrador)->get(route('recibos-sueldo.descargar', $archivoB))->assertOk();
});

test('un cobrador puede descargar su propio recibo', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    Storage::disk('local')->put('recibos-sueldo/4/2026-03/a.pdf', 'contenido');
    $recibo = ReciboSueldo::factory()->create(['profesor_id' => $cobrador->id]);
    $archivo = ReciboSueldoArchivo::factory()->create(['recibo_sueldo_id' => $recibo->id, 'archivo' => 'recibos-sueldo/4/2026-03/a.pdf']);

    $this->actingAs($cobrador)->get(route('recibos-sueldo.descargar', $archivo))->assertOk();
});

test('un cobrador no puede descargar el recibo de otro', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    Storage::disk('local')->put('recibos-sueldo/6/2026-03/a.pdf', 'contenido');
    $recibo = ReciboSueldo::factory()->create();
    $archivo = ReciboSueldoArchivo::factory()->create(['recibo_sueldo_id' => $recibo->id, 'archivo' => 'recibos-sueldo/6/2026-03/a.pdf']);

    $this->actingAs($cobrador)->get(route('recibos-sueldo.descargar', $archivo))->assertForbidden();
});

test('un cobrador fuera de la red del colegio puede descargar su propio recibo', function () {
    config(['acceso.restriccion_de_red_habilitada' => true]);
    $cobrador = User::factory()->create()->assignRole('cobrador');
    Storage::disk('local')->put('recibos-sueldo/7/2026-03/a.pdf', 'contenido');
    $recibo = ReciboSueldo::factory()->create(['profesor_id' => $cobrador->id]);
    $archivo = ReciboSueldoArchivo::factory()->create(['recibo_sueldo_id' => $recibo->id, 'archivo' => 'recibos-sueldo/7/2026-03/a.pdf']);

    $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('recibos-sueldo.descargar', $archivo))
        ->assertOk();
});

/**
 * La excepción de red para recibos-sueldo.* solo salta el chequeo de IP --
 * la Policy de "a quién pertenece" se sigue evaluando igual que si viniera
 * de la red del colegio. Esto confirma que no hay forma de que, por estar
 * exceptuado de la restricción de red, un cobrador termine viendo el
 * recibo de otra persona.
 */
test('un cobrador fuera de la red del colegio NO puede descargar el recibo de otro', function () {
    config(['acceso.restriccion_de_red_habilitada' => true]);
    $cobrador = User::factory()->create()->assignRole('cobrador');
    Storage::disk('local')->put('recibos-sueldo/8/2026-03/a.pdf', 'contenido');
    $recibo = ReciboSueldo::factory()->create();
    $archivo = ReciboSueldoArchivo::factory()->create(['recibo_sueldo_id' => $recibo->id, 'archivo' => 'recibos-sueldo/8/2026-03/a.pdf']);

    $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('recibos-sueldo.descargar', $archivo))
        ->assertForbidden();
});
