<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Pago;
use App\Cobranzas\Models\PagoCuota;
use App\Cobranzas\Services\GeneradorDeComprobantePago;
use App\Core\Models\PeriodoLectivo;
use App\Core\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');
});

function pagoCuotaDescargable(): PagoCuota
{
    $periodo = PeriodoLectivo::factory()->activo()->create();
    $tutor = Tutor::factory()->create();
    $alumno = Alumno::factory()->primario()->create();
    $tutor->alumnos()->attach($alumno->id, ['vinculo' => 'madre', 'responsable_pago' => true]);

    $cuota = Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_lectivo_id' => $periodo->id,
        'monto_base' => 100_000,
        'monto' => 100_000,
    ]);
    $pago = Pago::factory()->create(['monto' => 100_000]);
    $pagoCuota = PagoCuota::factory()->create([
        'pago_id' => $pago->id,
        'cuota_id' => $cuota->id,
        'monto_aplicado' => 100_000,
    ]);

    app(GeneradorDeComprobantePago::class)->generar($pagoCuota);

    return $pagoCuota->fresh();
}

test('un cobrador puede descargar el comprobante', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $pagoCuota = pagoCuotaDescargable();

    $response = $this->actingAs($cobrador)->get(route('pagos.comprobantes.descargar', $pagoCuota));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('un profesor no puede descargar el comprobante', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    $pagoCuota = pagoCuotaDescargable();

    $this->actingAs($profesor)->get(route('pagos.comprobantes.descargar', $pagoCuota))->assertForbidden();
});

test('si todavia no se genero el pdf, devuelve 404 en vez de un archivo vacio', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $pago = Pago::factory()->create();
    $cuota = Cuota::factory()->create();
    $pagoCuota = PagoCuota::factory()->create(['pago_id' => $pago->id, 'cuota_id' => $cuota->id, 'pdf_path' => null]);

    $this->actingAs($cobrador)->get(route('pagos.comprobantes.descargar', $pagoCuota))->assertNotFound();
});
