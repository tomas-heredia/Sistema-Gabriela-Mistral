<?php

use App\Core\Models\User;
use App\Sueldos\Livewire\RecibosSueldo\Listado;
use App\Sueldos\Models\ReciboSueldo;
use App\Sueldos\Models\ReciboSueldoArchivo;
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

test('un administrador puede eliminar un recibo, junto con todos sus archivos', function () {
    Storage::fake('local');
    Storage::disk('local')->put('recibos-sueldo/1/2026-03/a.pdf', 'contenido');
    Storage::disk('local')->put('recibos-sueldo/1/2026-03/b.pdf', 'contenido');

    $administrador = User::factory()->create()->assignRole('administrador');
    $recibo = ReciboSueldo::factory()->create();
    ReciboSueldoArchivo::query()->where('recibo_sueldo_id', $recibo->id)->delete();
    ReciboSueldoArchivo::factory()->create(['recibo_sueldo_id' => $recibo->id, 'archivo' => 'recibos-sueldo/1/2026-03/a.pdf']);
    ReciboSueldoArchivo::factory()->create(['recibo_sueldo_id' => $recibo->id, 'archivo' => 'recibos-sueldo/1/2026-03/b.pdf']);

    Livewire::actingAs($administrador)->test(Listado::class)
        ->call('eliminar', $recibo->id);

    expect(ReciboSueldo::find($recibo->id))->toBeNull();
    Storage::disk('local')->assertMissing('recibos-sueldo/1/2026-03/a.pdf');
    Storage::disk('local')->assertMissing('recibos-sueldo/1/2026-03/b.pdf');
});

/**
 * Estos dos casos pegan por HTTP real (no Livewire::test()): el mount
 * inicial de Livewire::test() corre con el middleware deshabilitado y arma
 * su PROPIO Request interno (ver Livewire\Features\SupportTesting\
 * InitialRender::makeInitialRequest), que ignora cualquier REMOTE_ADDR
 * seteado a mano antes de llamarlo -- mismo tipo de limitación que ya
 * documenta CLAUDE.md para Livewire::test() y el layout/naming de
 * componentes. Para probar algo que depende de la IP del pedido hay que
 * pegarle a la ruta real.
 */
test('un cobrador fuera de la red del colegio solo ve sus propios recibos', function () {
    config(['acceso.restriccion_de_red_habilitada' => true]);

    $cobrador = User::factory()->create()->assignRole('cobrador');
    $otroCobrador = User::factory()->create()->assignRole('cobrador');
    ReciboSueldo::factory()->create(['profesor_id' => $cobrador->id, 'periodo' => '2026-03']);
    ReciboSueldo::factory()->create(['profesor_id' => $otroCobrador->id, 'periodo' => '2026-03']);

    $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('recibos-sueldo.index'))
        ->assertOk()
        ->assertSee('2026-03')
        ->assertDontSee($otroCobrador->name)
        ->assertDontSee('Nuevo recibo');
});

test('un cobrador en la red del colegio ve el listado completo y el boton de nuevo recibo, pero no eliminar', function () {
    config(['acceso.restriccion_de_red_habilitada' => true]);

    $cobrador = User::factory()->create()->assignRole('cobrador');
    $otroCobrador = User::factory()->create()->assignRole('cobrador');
    ReciboSueldo::factory()->create(['profesor_id' => $cobrador->id, 'periodo' => '2026-03']);
    ReciboSueldo::factory()->create(['profesor_id' => $otroCobrador->id, 'periodo' => '2026-03']);

    $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '100.90.12.34'])
        ->get(route('recibos-sueldo.index'))
        ->assertOk()
        ->assertSee('2026-03')
        ->assertSee($otroCobrador->name)
        ->assertSee('Nuevo recibo')
        ->assertDontSee('Eliminar');
});
