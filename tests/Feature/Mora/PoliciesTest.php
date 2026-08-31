<?php

use App\Core\Models\User;
use App\Mora\Models\NotificacionMora;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('administrador puede viewAny sobre NotificacionMora', function () {
    $user = User::factory()->create()->assignRole('administrador');

    expect($user->can('viewAny', NotificacionMora::class))->toBeTrue();
});

test('cobrador puede viewAny sobre NotificacionMora', function () {
    $user = User::factory()->create()->assignRole('cobrador');

    expect($user->can('viewAny', NotificacionMora::class))->toBeTrue();
});

test('profesor no puede viewAny sobre NotificacionMora', function () {
    $user = User::factory()->create()->assignRole('profesor');

    expect($user->can('viewAny', NotificacionMora::class))->toBeFalse();
});
