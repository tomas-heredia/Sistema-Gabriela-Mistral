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
        ->set('archivo', UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf'))
        ->call('guardar')
        ->assertRedirect(route('recibos-sueldo.index'));

    $recibo = ReciboSueldo::where('profesor_id', $profesor->id)->where('periodo', '2026-03')->first();
    expect($recibo)->not->toBeNull()
        ->and($recibo->cargado_por_id)->toBe($administrador->id);

    Storage::disk('local')->assertExists($recibo->archivo);
});

test('rechaza un segundo recibo para el mismo profesor y periodo', function () {
    Storage::fake('local');

    $administrador = User::factory()->create()->assignRole('administrador');
    $profesor = User::factory()->create()->assignRole('profesor');
    ReciboSueldo::factory()->create(['profesor_id' => $profesor->id, 'periodo' => '2026-03']);

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('profesor_id', (string) $profesor->id)
        ->set('periodo', '2026-03')
        ->set('archivo', UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf'))
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
        ->set('archivo', UploadedFile::fake()->image('recibo.png'))
        ->call('guardar')
        ->assertHasErrors(['archivo' => 'mimes']);
});

test('un profesor no puede montar el componente', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Formulario::class)->assertForbidden();
});
