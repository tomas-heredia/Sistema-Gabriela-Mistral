<?php

use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;

/**
 * `Livewire::test()` monta el componente por su CLASE directamente, así que
 * nunca ejercita la resolución nombre→clase que usa un pedido AJAX real
 * (`/livewire/update`). Como todos los componentes de este proyecto viven
 * fuera del namespace `App\Livewire` que Livewire asume por convención, esa
 * resolución inversa fallaba en producción con un 419 "release token
 * mismatch" (nombre engañoso: en realidad es "no encontré la clase para
 * este nombre") en cuanto el usuario hacía cualquier acción — búsqueda,
 * paginación, guardar — después de la carga inicial. Se soluciona
 * registrando cada componente con un alias explícito vía
 * `Livewire::component()` en AppServiceProvider. Este test pega contra la
 * ruta real y después hace un segundo pedido real a /livewire/update para
 * atrapar esta clase de bug, que ningún `Livewire::test()` puede ver.
 */
function llamarLivewireUpdate(string $html, string $nombreComponente): TestResponse
{
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $coincidencias);

    $snapshot = null;

    foreach ($coincidencias[1] as $crudo) {
        $decodificado = html_entity_decode($crudo, ENT_QUOTES);
        $datos = json_decode($decodificado, true);

        if (($datos['memo']['name'] ?? null) === $nombreComponente) {
            $snapshot = $decodificado;
            break;
        }
    }

    expect($snapshot)->not->toBeNull("No se encontró wire:snapshot para el componente [{$nombreComponente}] en la página.");

    return test()->postJson('/livewire/update', [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => [],
            'calls' => [],
        ]],
    ], ['X-Livewire' => 'true']);
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->administrador = User::factory()->create()->assignRole('administrador');
});

test('cada pantalla de página completa resuelve su nombre de componente en un pedido AJAX real', function (string $ruta, string $nombreComponente) {
    $html = $this->actingAs($this->administrador)->get(route($ruta))->getContent();

    llamarLivewireUpdate($html, $nombreComponente)->assertOk();
})->with([
    'tutores.index' => ['tutores.index', 'tutores.listado'],
    'tutores.crear' => ['tutores.crear', 'tutores.formulario'],
    'alumnos.index' => ['alumnos.index', 'alumnos.listado'],
    'alumnos.crear' => ['alumnos.crear', 'alumnos.formulario'],
    'pagos.index' => ['pagos.index', 'pagos.listado'],
    'pagos.registrar' => ['pagos.registrar', 'pagos.registrar'],
]);
