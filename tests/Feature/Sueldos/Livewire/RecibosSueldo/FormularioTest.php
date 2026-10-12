<?php

use App\Core\Models\User;
use App\Sueldos\Livewire\RecibosSueldo\Formulario;
use App\Sueldos\Models\ReciboSueldo;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('cargar un recibo lo guarda con el archivo y quien lo cargo', function () {
    Storage::fake('local');

    $administrador = User::factory()->create()->assignRole('administrador');
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('profesor_id', (string) $profesor->id)
        ->set('periodo', '2026-03')
        ->set('archivos', [UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf')])
        ->call('guardar')
        ->assertRedirect(route('recibos-sueldo.index'));

    $recibo = ReciboSueldo::where('profesor_id', $profesor->id)->where('periodo', '2026-03')->first();
    expect($recibo)->not->toBeNull()
        ->and($recibo->cargado_por_id)->toBe($administrador->id)
        ->and($recibo->archivos)->toHaveCount(1);

    Storage::disk('local')->assertExists($recibo->archivos->first()->archivo);
});

test('cargar un recibo con mas de un archivo guarda todos para el mismo periodo', function () {
    Storage::fake('local');

    $administrador = User::factory()->create()->assignRole('administrador');
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('profesor_id', (string) $profesor->id)
        ->set('periodo', '2026-03')
        ->set('archivos', [
            UploadedFile::fake()->create('recibo-1.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('recibo-2.pdf', 100, 'application/pdf'),
        ])
        ->call('guardar')
        ->assertRedirect(route('recibos-sueldo.index'));

    $recibo = ReciboSueldo::where('profesor_id', $profesor->id)->where('periodo', '2026-03')->first();
    expect($recibo->archivos)->toHaveCount(2);

    $recibo->archivos->each(fn ($archivo) => Storage::disk('local')->assertExists($archivo->archivo));
});

test('rechaza un segundo recibo para el mismo profesor y periodo', function () {
    Storage::fake('local');

    $administrador = User::factory()->create()->assignRole('administrador');
    $profesor = User::factory()->create()->assignRole('profesor');
    ReciboSueldo::factory()->create(['profesor_id' => $profesor->id, 'periodo' => '2026-03']);

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('profesor_id', (string) $profesor->id)
        ->set('periodo', '2026-03')
        ->set('archivos', [UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf')])
        ->call('guardar')
        ->assertHasErrors(['periodo' => 'unique']);
});

test('rechaza un archivo que no sea pdf', function () {
    Storage::fake('local');

    $administrador = User::factory()->create()->assignRole('administrador');
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('profesor_id', (string) $profesor->id)
        ->set('periodo', '2026-03')
        ->set('archivos', [UploadedFile::fake()->image('recibo.png')])
        ->call('guardar')
        ->assertHasErrors(['archivos.0' => 'mimes']);
});

test('rechaza guardar sin ningun archivo', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('profesor_id', (string) $profesor->id)
        ->set('periodo', '2026-03')
        ->call('guardar')
        ->assertHasErrors(['archivos' => 'required']);
});

test('un profesor no puede montar el componente', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Formulario::class)->assertForbidden();
});
