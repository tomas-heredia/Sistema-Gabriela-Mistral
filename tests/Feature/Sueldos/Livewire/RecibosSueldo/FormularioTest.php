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

test('un cobrador fuera de la red del colegio no puede acceder al formulario de carga', function () {
    config(['acceso.restriccion_de_red_habilitada' => true]);
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('recibos-sueldo.crear'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

/**
 * Livewire::test() arma su PROPIO Request para el mount inicial, con el
 * middleware deshabilitado, e ignora cualquier REMOTE_ADDR seteado a mano
 * antes de llamarlo (ver la nota equivalente en ListadoTest) -- por eso
 * acá se verifica el acceso real por HTTP, y la mecánica de guardado se
 * prueba aparte sin simular una IP (ver el test siguiente).
 */
test('un cobrador en la red del colegio puede acceder al formulario de carga', function () {
    config(['acceso.restriccion_de_red_habilitada' => true]);

    $cobrador = User::factory()->create()->assignRole('cobrador');

    $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '100.90.12.34'])
        ->get(route('recibos-sueldo.crear'))
        ->assertOk();
});

test('un cobrador puede cargarle un recibo a otro empleado', function () {
    Storage::fake('local');

    $cobrador = User::factory()->create()->assignRole('cobrador');
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($cobrador)->test(Formulario::class)
        ->set('profesor_id', (string) $profesor->id)
        ->set('periodo', '2026-03')
        ->set('archivos', [UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf')])
        ->call('guardar')
        ->assertRedirect(route('recibos-sueldo.index'));

    expect(ReciboSueldo::where('profesor_id', $profesor->id)->where('periodo', '2026-03')->exists())->toBeTrue();
});

test('se le puede cargar un recibo a un cobrador', function () {
    Storage::fake('local');

    $administrador = User::factory()->create()->assignRole('administrador');
    $cobrador = User::factory()->create()->assignRole('cobrador');

    Livewire::actingAs($administrador)->test(Formulario::class)
        ->set('profesor_id', (string) $cobrador->id)
        ->set('periodo', '2026-03')
        ->set('archivos', [UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf')])
        ->call('guardar')
        ->assertRedirect(route('recibos-sueldo.index'));

    $recibo = ReciboSueldo::where('profesor_id', $cobrador->id)->where('periodo', '2026-03')->first();
    expect($recibo)->not->toBeNull();
});
