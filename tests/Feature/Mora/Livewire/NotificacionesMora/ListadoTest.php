<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\TipoCuota;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use App\Mora\Livewire\NotificacionesMora\Listado;
use App\Mora\Models\Enums\EstadoNotificacion;
use App\Mora\Models\NotificacionMora;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function tutorEnMora(string $nombre, int $montoVencido, ?PeriodoLectivo $periodo = null, ?string $fechaVencimiento = null): Tutor
{
    $periodo ??= PeriodoLectivo::factory()->activo()->create();
    $tutor = Tutor::factory()->create(['nombre' => $nombre]);
    $alumno = Alumno::factory()->primario()->create();
    $tutor->alumnos()->attach($alumno->id, ['vinculo' => 'madre', 'responsable_pago' => true]);

    Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_lectivo_id' => $periodo->id,
        'tipo' => TipoCuota::Mensualidad,
        'monto_base' => $montoVencido,
        'monto' => $montoVencido,
        'estado' => EstadoCuota::Pendiente,
        'fecha_vencimiento' => $fechaVencimiento ?? now()->subMonth(),
    ]);

    return $tutor;
}

test('lista a los tutores con cuotas vencidas y su monto adeudado', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    tutorEnMora('Marta Gómez', 100_000);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertSee('Marta Gómez')
        ->assertSee('1.000,00');
});

test('un tutor sin cuotas vencidas no aparece en la lista', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $tutor = Tutor::factory()->create(['nombre' => 'Al Día']);
    $alumno = Alumno::factory()->primario()->create();
    $tutor->alumnos()->attach($alumno->id, ['vinculo' => 'madre', 'responsable_pago' => true]);

    Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_lectivo_id' => $periodo->id,
        'estado' => EstadoCuota::Pagada,
        'fecha_vencimiento' => now()->subMonth(),
    ]);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertDontSee('Al Día');
});

test('busca por nombre o dni del tutor', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $tutor = tutorEnMora('Carla Díaz', 100_000);
    $tutor->update(['dni' => '30111222']);
    tutorEnMora('Bruno Pérez', 100_000);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->set('busqueda', '30111222')
        ->assertSee('Carla Díaz')
        ->assertDontSee('Bruno Pérez');
});

test('filtra por rango de fechas de vencimiento', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();

    tutorEnMora('Deuda Vieja', 100_000, $periodo, now()->subMonths(3)->format('Y-m-d'));
    tutorEnMora('Deuda Reciente', 100_000, $periodo, now()->subDays(5)->format('Y-m-d'));

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->set('desde', now()->subMonths(4)->format('Y-m-d'))
        ->set('hasta', now()->subMonths(2)->format('Y-m-d'))
        ->assertSee('Deuda Vieja')
        ->assertDontSee('Deuda Reciente');
});

test('un profesor no puede montar el componente', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Listado::class)->assertForbidden();
});

test('administrador elimina la deuda de un tutor: anula sus cuotas vencidas y el tutor desaparece de mora', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $tutor = tutorEnMora('Marta Gómez', 100_000);

    Livewire::actingAs($administrador)->test(Listado::class)
        ->assertSee('Marta Gómez')
        ->assertSee('Eliminar deuda')
        ->call('eliminarDeuda', $tutor->id)
        ->assertDontSee('Marta Gómez');

    $cuota = Cuota::where('alumno_id', $tutor->alumnos()->first()->id)->first();
    expect($cuota->estado)->toBe(EstadoCuota::Anulada);
});

test('eliminar deuda no anula cuotas futuras aun no vencidas', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $tutor = tutorEnMora('Marta Gómez', 100_000, $periodo, now()->subMonth()->format('Y-m-d'));
    $alumno = $tutor->alumnos()->first();

    $cuotaFutura = Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_lectivo_id' => $periodo->id,
        'tipo' => TipoCuota::Mensualidad,
        'mes' => 1, // fuera del rango 3-12 que usa tutorEnMora() -- evita chocar con su cuota al azar.
        'estado' => EstadoCuota::Pendiente,
        'fecha_vencimiento' => now()->addMonth(),
    ]);

    Livewire::actingAs($administrador)->test(Listado::class)
        ->call('eliminarDeuda', $tutor->id);

    expect($cuotaFutura->fresh()->estado)->toBe(EstadoCuota::Pendiente);
});

test('eliminar deuda cancela cualquier aviso de mora ya en cola para ese tutor', function () {
    $administrador = User::factory()->create()->assignRole('administrador');
    $tutor = tutorEnMora('Marta Gómez', 100_000);
    $notificacion = NotificacionMora::factory()->create([
        'tutor_id' => $tutor->id,
        'estado' => EstadoNotificacion::Pendiente,
    ]);

    Livewire::actingAs($administrador)->test(Listado::class)
        ->call('eliminarDeuda', $tutor->id);

    expect($notificacion->fresh()->estado)->toBe(EstadoNotificacion::Cancelado);
});

test('un cobrador no ve el boton de eliminar deuda ni puede ejecutarlo', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $tutor = tutorEnMora('Marta Gómez', 100_000);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->assertDontSee('Eliminar deuda')
        ->call('eliminarDeuda', $tutor->id)
        ->assertForbidden();
});
