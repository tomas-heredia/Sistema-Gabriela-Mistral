<?php

use App\Core\Models\User;
use App\Sueldos\Livewire\RecibosSueldo\Listado;
use App\Sueldos\Models\ReciboSueldo;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('un profesor solo ve sus propios recibos', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    $otroProfesor = User::factory()->create()->assignRole('profesor');
    ReciboSueldo::factory()->create(['profesor_id' => $profesor->id, 'periodo' => '2026-03']);
    ReciboSueldo::factory()->create(['profesor_id' => $otroProfesor->id, 'periodo' => '2026-03']);

    Livewire::actingAs($profesor)->test(Listado::class)
        ->assertSee('2026-03')
        ->assertDontSee($otroProfesor->name);
});

test('un administrador ve todos los recibos y puede buscar por nombre', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $profesor = User::factory()->create(['name' => 'Marta Gómez'])->assignRole('profesor');
    ReciboSueldo::factory()->create(['profesor_id' => $profesor->id]);

    Livewire::actingAs($administrador)->test(Listado::class)
        ->assertSee('Marta Gómez')
        ->set('busqueda', 'Marta')
        ->assertSee('Marta Gómez')
        ->set('busqueda', 'Nadie Así')
        ->assertDontSee('Marta Gómez');
});

test('un profesor no ve el boton de nuevo recibo ni eliminar', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    ReciboSueldo::factory()->create(['profesor_id' => $profesor->id]);

    Livewire::actingAs($profesor)->test(Listado::class)
        ->assertDontSee('Nuevo recibo')
        ->assertDontSee('Eliminar');
});

test('un administrador puede eliminar un recibo, junto con su archivo', function () {
    Storage::fake('local');
    Storage::disk('local')->put('recibos-sueldo/1/2026-03.pdf', 'contenido');

    $administrador = User::factory()->create()->assignRole('administrador');
    $recibo = ReciboSueldo::factory()->create(['archivo' => 'recibos-sueldo/1/2026-03.pdf']);

    Livewire::actingAs($administrador)->test(Listado::class)
        ->call('eliminar', $recibo->id);

    expect(ReciboSueldo::find($recibo->id))->toBeNull();
    Storage::disk('local')->assertMissing('recibos-sueldo/1/2026-03.pdf');
});

test('el cobrador no puede montar el componente', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    Livewire::actingAs($cobrador)->test(Listado::class)->assertForbidden();
});
