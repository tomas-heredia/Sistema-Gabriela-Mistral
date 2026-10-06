<?php

use App\Core\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['acceso.restriccion_de_red_habilitada' => true]);
});

test('un cobrador fuera de la red del colegio recibe 403', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $response = $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('dashboard'));

    $response->assertForbidden();
});

test('un cobrador conectado por la VPN de Tailscale entra sin problema', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $response = $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '100.90.12.34'])
        ->get(route('dashboard'));

    $response->assertOk();
});

test('un administra_alumnos fuera de la red del colegio recibe 403', function () {
    $gestor = User::factory()->create()->assignRole('administra_alumnos');

    $response = $this->actingAs($gestor)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('dashboard'));

    $response->assertForbidden();
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

test('un X-Forwarded-For que no viene de loopback se ignora', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $response = $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->withHeaders(['X-Forwarded-For' => '100.69.170.0'])
        ->get(route('dashboard'));

    $response->assertForbidden();
});

test('con la restriccion apagada, un cobrador entra desde cualquier red', function () {
    config(['acceso.restriccion_de_red_habilitada' => false]);
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $response = $this->actingAs($cobrador)
        ->withServerVariables(['REMOTE_ADDR' => '190.191.10.20'])
        ->get(route('dashboard'));

    $response->assertOk();
});
