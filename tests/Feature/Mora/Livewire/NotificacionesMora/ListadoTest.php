<?php

use App\Alumnos\Models\Tutor;
use App\Core\Models\User;
use App\Mora\Livewire\NotificacionesMora\Listado;
use App\Mora\Models\Enums\EstadoNotificacion;
use App\Mora\Models\NotificacionMora;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('lista las notificaciones con su estado', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $tutor = Tutor::factory()->create(['nombre' => 'Marta Gómez']);
    NotificacionMora::factory()->create(['tutor_id' => $tutor->id, 'estado' => EstadoNotificacion::Enviado]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertSee('Marta Gómez')
        ->assertSee('Enviado');
});

test('busca por nombre o dni del tutor', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $tutor = Tutor::factory()->create(['nombre' => 'Carla Díaz', 'dni' => '30111222']);
    NotificacionMora::factory()->create(['tutor_id' => $tutor->id]);
    $otroTutor = Tutor::factory()->create(['nombre' => 'Bruno Pérez']);
    NotificacionMora::factory()->create(['tutor_id' => $otroTutor->id]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->set('busqueda', '30111222')
        ->assertSee('Carla Díaz')
        ->assertDontSee('Bruno Pérez');
});

test('filtra por estado', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $tutorPendiente = Tutor::factory()->create(['nombre' => 'Tutor Pendiente']);
    NotificacionMora::factory()->create(['tutor_id' => $tutorPendiente->id, 'estado' => EstadoNotificacion::Pendiente]);
    $tutorCancelado = Tutor::factory()->create(['nombre' => 'Tutor Cancelado']);
    NotificacionMora::factory()->create(['tutor_id' => $tutorCancelado->id, 'estado' => EstadoNotificacion::Cancelado]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->set('estado', EstadoNotificacion::Cancelado->value)
        ->assertSee('Tutor Cancelado')
        ->assertDontSee('Tutor Pendiente');
});

test('un profesor no puede montar el componente', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Listado::class)->assertForbidden();
});
