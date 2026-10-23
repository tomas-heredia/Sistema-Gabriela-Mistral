<?php

use App\Core\Models\User;
use App\Sueldos\Models\ReciboSueldo;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['acceso.restriccion_de_red_habilitada' => true]);
});

test('un cobrador fuera de la red del colegio es deslogueado y mandado al login', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $response = $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('dashboard'));

    $response->assertRedirect(route('login'));
    expect(session('error'))->not->toBeNull();
    $this->assertGuest();
});

test('un cobrador conectado por la VPN de Tailscale entra sin problema', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $response = $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '100.90.12.34'])
        ->get(route('dashboard'));

    $response->assertOk();
});

test('un profesor con permiso de cargar boletines no tiene restriccion de red', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    $profesor->givePermissionTo('cargar_boletines');

    $response = $this->actingAs($profesor)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('dashboard'));

    $response->assertOk();
});

test('un administrador no tiene restriccion de red', function () {
    $admin = User::factory()->create()->assignRole('administrador');

    $response = $this->actingAs($admin)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('dashboard'));

    $response->assertOk();
});

test('un profesor no tiene restriccion de red', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    $response = $this->actingAs($profesor)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('dashboard'));

    $response->assertOk();
});

test('un invitado puede ver el login sin importar la red', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('login'));

    $response->assertOk();
});

test('confia en el X-Forwarded-For de tailscale serve, que reenvia por loopback', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $response = $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders(['X-Forwarded-For' => '100.69.170.0'])
        ->get(route('dashboard'));

    $response->assertOk();
});

test('confia en el X-Forwarded-For aunque docker lo reescriba al gateway interno', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $response = $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '172.16.1.1'])
        ->withHeaders(['X-Forwarded-For' => '100.69.170.0'])
        ->get(route('dashboard'));

    $response->assertOk();
});

test('un X-Forwarded-For que no viene de loopback se ignora', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $response = $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->withHeaders(['X-Forwarded-For' => '100.69.170.0'])
        ->get(route('dashboard'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('un cobrador bloqueado puede volver a entrar como otro usuario sin restriccion', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $admin = User::factory()->create()->assignRole('administrador');

    $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('dashboard'));

    $this->assertGuest();

    $response = $this->actingAs($admin)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('dashboard'));

    $response->assertOk();
});

test('un cobrador fuera de la red del colegio solo ve sus propios recibos de sueldo en el listado', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $otroCobrador = User::factory()->create()->assignRole('cobrador');
    ReciboSueldo::factory()->create(['profesor_id' => $cobrador->id, 'periodo' => '2026-03']);
    ReciboSueldo::factory()->create(['profesor_id' => $otroCobrador->id, 'periodo' => '2026-03']);

    $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('recibos-sueldo.index'))
        ->assertOk()
        ->assertSee('2026-03')
        ->assertDontSee($otroCobrador->name);
});

test('un cobrador fuera de la red del colegio puede entrar a sus recibos de sueldo', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $response = $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('recibos-sueldo.index'));

    $response->assertOk();
});

test('un cobrador fuera de la red del colegio sigue bloqueado para el resto del sistema', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $response = $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('pagos.index'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('un cobrador fuera de la red del colegio puede interactuar via AJAX con el listado de sueldos', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $snapshot = json_encode(['memo' => ['name' => 'recibos-sueldo.listado']]);

    $response = $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->post('/livewire/update', [
            'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]],
        ]);

    // No se verifica el status exacto: el snapshot es fabricado a mano
    // (sin checksum real) y Livewire lo va a rechazar por su cuenta con su
    // propio error -- lo que importa acá es que el middleware de red no
    // fue quien cortó el pedido (no redirige a /login ni desloguea).
    expect($response->status())->not->toBe(302);
    $this->assertAuthenticated();
});

test('un cobrador fuera de la red del colegio no puede colar otro componente en la excepcion de sueldos', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $snapshotSueldos = json_encode(['memo' => ['name' => 'recibos-sueldo.listado']]);
    $snapshotPagos = json_encode(['memo' => ['name' => 'pagos.listado']]);

    $response = $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->post('/livewire/update', [
            'components' => [
                ['snapshot' => $snapshotSueldos, 'updates' => [], 'calls' => []],
                ['snapshot' => $snapshotPagos, 'updates' => [], 'calls' => []],
            ],
        ]);

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('con la restriccion apagada, un cobrador entra desde cualquier red', function () {
    config(['acceso.restriccion_de_red_habilitada' => false]);
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $response = $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('dashboard'));

    $response->assertOk();
});
