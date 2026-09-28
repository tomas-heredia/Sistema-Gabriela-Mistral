<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('un cobrador puede generar el pdf de morosos', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $tutor = Tutor::factory()->create(['nombre' => 'Marta Gómez']);
    $alumno = Alumno::factory()->primario()->create();
    $tutor->alumnos()->attach($alumno->id, ['vinculo' => 'madre', 'responsable_pago' => true]);
    Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_lectivo_id' => $periodo->id,
        'estado' => EstadoCuota::Pendiente,
        'fecha_vencimiento' => now()->subMonth(),
    ]);

    $response = $this->actingAs($cobrador)->get(route('mora.pdf'));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('un profesor no puede generar el pdf de morosos', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    $this->actingAs($profesor)->get(route('mora.pdf'))->assertForbidden();
});
