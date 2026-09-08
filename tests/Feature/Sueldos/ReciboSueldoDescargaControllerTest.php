<?php

use App\Core\Models\User;
use App\Sueldos\Models\ReciboSueldo;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');
});

test('un profesor puede descargar su propio recibo', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    Storage::disk('local')->put('recibos-sueldo/1/2026-03.pdf', 'contenido');
    $recibo = ReciboSueldo::factory()->create(['profesor_id' => $profesor->id, 'archivo' => 'recibos-sueldo/1/2026-03.pdf']);

    $this->actingAs($profesor)->get(route('recibos-sueldo.descargar', $recibo))->assertOk();
});

test('un profesor no puede descargar el recibo de otro', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    $otroProfesor = User::factory()->create()->assignRole('profesor');
    Storage::disk('local')->put('recibos-sueldo/2/2026-03.pdf', 'contenido');
    $recibo = ReciboSueldo::factory()->create(['profesor_id' => $otroProfesor->id, 'archivo' => 'recibos-sueldo/2/2026-03.pdf']);

    $this->actingAs($profesor)->get(route('recibos-sueldo.descargar', $recibo))->assertForbidden();
});

test('un administrador puede descargar cualquier recibo', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    Storage::disk('local')->put('recibos-sueldo/3/2026-03.pdf', 'contenido');
    $recibo = ReciboSueldo::factory()->create(['archivo' => 'recibos-sueldo/3/2026-03.pdf']);

    $this->actingAs($administrador)->get(route('recibos-sueldo.descargar', $recibo))->assertOk();
});

test('el cobrador no puede descargar ningun recibo', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    Storage::disk('local')->put('recibos-sueldo/4/2026-03.pdf', 'contenido');
    $recibo = ReciboSueldo::factory()->create(['archivo' => 'recibos-sueldo/4/2026-03.pdf']);

    $this->actingAs($cobrador)->get(route('recibos-sueldo.descargar', $recibo))->assertForbidden();
});
