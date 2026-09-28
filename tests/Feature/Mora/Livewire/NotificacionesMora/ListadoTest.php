<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\TipoCuota;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use App\Mora\Livewire\NotificacionesMora\Listado;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function tutorEnMora(string $nombre, int $montoVencido, ?PeriodoLectivo $periodo = null): Tutor
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
        'fecha_vencimiento' => now()->subMonth(),
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

test('filtra por periodo lectivo', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodoViejo = PeriodoLectivo::factory()->create(['nombre' => '2025']);
    $periodoActivo = PeriodoLectivo::factory()->activo()->create(['nombre' => '2026']);

    tutorEnMora('Deuda 2025', 100_000, $periodoViejo);
    tutorEnMora('Deuda 2026', 100_000, $periodoActivo);

    Livewire::actingAs($cobrador)->test(Listado::class)
        ->set('periodoLectivoId', (string) $periodoViejo->id)
        ->assertSee('Deuda 2025')
        ->assertDontSee('Deuda 2026');
});

test('un profesor no puede montar el componente', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    Livewire::actingAs($profesor)->test(Listado::class)->assertForbidden();
});
