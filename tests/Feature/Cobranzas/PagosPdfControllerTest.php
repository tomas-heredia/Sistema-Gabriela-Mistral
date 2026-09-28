<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Pago;
use App\Cobranzas\Models\PagoCuota;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function pagoConAlumno(string $numeroRecibo, int $importe, string $fecha, ?PeriodoLectivo $periodo = null, bool $anulado = false): Pago
{
    $periodo ??= PeriodoLectivo::factory()->activo()->create();
    $tutor = Tutor::factory()->create();
    $alumno = Alumno::factory()->primario()->create();
    $tutor->alumnos()->attach($alumno->id, ['vinculo' => 'madre', 'responsable_pago' => true]);

    $cuota = Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_lectivo_id' => $periodo->id,
        'monto' => $importe,
        'estado' => EstadoCuota::Pagada,
    ]);

    $pago = Pago::factory()->create([
        'tutor_id' => $tutor->id,
        'monto' => $importe,
        'fecha' => $fecha,
        'numero_recibo' => $numeroRecibo,
        'anulado_at' => $anulado ? now() : null,
    ]);

    PagoCuota::factory()->create([
        'pago_id' => $pago->id,
        'cuota_id' => $cuota->id,
        'monto_aplicado' => $importe,
    ]);

    return $pago;
}

test('un cobrador puede generar el pdf de pagos', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    pagoConAlumno('000001', 100_000, '2026-03-10');

    $response = $this->actingAs($cobrador)->get(route('pagos.pdf'));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('un profesor no puede generar el pdf de pagos', function () {
    $profesor = User::factory()->create()->assignRole('profesor');

    $this->actingAs($profesor)->get(route('pagos.pdf'))->assertForbidden();
});
