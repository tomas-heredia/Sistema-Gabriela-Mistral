<?php

use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Volt\Volt;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.login');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password');

    $component->call('login');

    $component
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('un usuario desactivado no puede iniciar sesion aunque la contraseña sea correcta', function () {
    $user = User::factory()->inactivo()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password');

    $component->call('login');

    $component->assertHasErrors();
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'wrong-password');

    $component->call('login');

    $component
        ->assertHasErrors()
        ->assertNoRedirect();

    $this->assertGuest();
});

test('navigation menu can be rendered', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    $response
        ->assertOk()
        ->assertSeeVolt('layout.navigation');
});

/**
 * El mount inicial de Volt::test()/Livewire::test() corre con el middleware
 * deshabilitado y arma su propio Request (ver la nota equivalente en
 * ListadoTest de Sueldos) -- pero acá no hace falta pegarle por HTTP real:
 * ese Request interno siempre trae REMOTE_ADDR 127.0.0.1, que de por sí no
 * es una IP de la red del colegio, así que alcanza con la restricción de
 * red prendida o apagada (sin necesidad de simular una IP puntual) para
 * ejercitar las dos ramas del redirect.
 */
test('un cobrador fuera de la red del colegio es mandado a sus recibos de sueldo al loguearse, no al dashboard', function () {
    $this->seed(RoleSeeder::class);
    config(['acceso.restriccion_de_red_habilitada' => true]);
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $cobrador->email)
        ->set('form.password', 'password');

    $component->call('login');

    $component
        ->assertHasNoErrors()
        ->assertRedirect(route('recibos-sueldo.index', absolute: false));
});

test('con la restriccion de red apagada, un cobrador es mandado al dashboard como cualquier otro rol', function () {
    $this->seed(RoleSeeder::class);
    config(['acceso.restriccion_de_red_habilitada' => false]);
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $cobrador->email)
        ->set('form.password', 'password');

    $component->call('login');

    $component
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('layout.navigation');

    $component->call('logout');

    $component
        ->assertHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
});
