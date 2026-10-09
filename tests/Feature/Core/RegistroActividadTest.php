<?php

use App\Boletines\Models\Boletin;
use App\Boletines\Models\BoletinTrimestre;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

afterEach(function () {
    foreach (glob(storage_path('app/logs/actividad-*.log')) as $archivo) {
        @unlink($archivo);
    }
});

function leerLogDeActividad(): string
{
    $archivo = storage_path('app/logs/actividad-'.now()->format('Y-m-d').'.log');

    return file_exists($archivo) ? file_get_contents($archivo) : '';
}

test('iniciar sesion queda registrado en el archivo de log de actividad', function () {
    $usuario = User::factory()->create();

    Volt::test('pages.auth.login')
        ->set('form.email', $usuario->email)
        ->set('form.password', 'password')
        ->call('login');

    $log = leerLogDeActividad();

    expect($log)->toContain('Inició sesión')
        ->and($log)->toContain($usuario->name)
        ->and($log)->toContain('"ip"');
});

test('cerrar sesion queda registrado en el archivo de log de actividad', function () {
    $usuario = User::factory()->create();
    $this->actingAs($usuario);

    Volt::test('layout.navigation')->call('logout');

    $log = leerLogDeActividad();

    expect($log)->toContain('Cerró sesión')
        ->and($log)->toContain($usuario->name);
});

test('cargar un borrador de trimestre queda registrado en el archivo de log de actividad', function () {
    $profesor = User::factory()->create(['name' => 'Marta Gómez'])->assignRole('profesor');
    $profesor->givePermissionTo('cargar_boletines');
    $boletin = Boletin::factory()->create();
    $boletin->alumno->update(['nombre' => 'Ana Pérez']);
    $trimestre = BoletinTrimestre::factory()->create(['boletin_id' => $boletin->id, 'trimestre' => 1]);

    $trimestre->cargar(['promedio_anual' => '9'], $profesor);

    $log = leerLogDeActividad();

    expect($log)->toContain('Cargó borrador del trimestre 1')
        ->and($log)->toContain('Ana Pérez')
        ->and($log)->toContain('Marta Gómez');
});

test('confirmar y enviar un trimestre queda registrado en el archivo de log de actividad', function () {
    Queue::fake();

    $cobrador = User::factory()->create(['name' => 'Juan Cobrador'])->assignRole('cobrador');
    $trimestre = BoletinTrimestre::factory()->cargado()->create(['trimestre' => 2]);

    $this->actingAs($cobrador);
    $trimestre->confirmarYEnviar();

    $log = leerLogDeActividad();

    expect($log)->toContain('Confirmó y envió el trimestre 2')
        ->and($log)->toContain('Juan Cobrador');
});
