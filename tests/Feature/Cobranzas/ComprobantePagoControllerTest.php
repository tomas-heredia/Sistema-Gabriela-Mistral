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

    app(GeneradorDeComprobantePago::class)->generar(collect([$pagoCuota]));

    return $pagoCuota->fresh();
}

test('un cobrador puede descargar el comprobante', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $pagoCuota = pagoCuotaDescargable();

    $response = $this->actingAs($cobrador)->get(route('pagos.comprobantes.descargar', $pagoCuota->numero_recibo));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
});

test('un profesor no puede descargar el comprobante', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    $pagoCuota = pagoCuotaDescargable();

    $this->actingAs($profesor)->get(route('pagos.comprobantes.descargar', $pagoCuota->numero_recibo))->assertForbidden();
});

test('si todavia no se genero el pdf, devuelve 404 en vez de un archivo vacio', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $pago = Pago::factory()->create();
    $cuota = Cuota::factory()->create();
    $pagoCuota = PagoCuota::factory()->create(['pago_id' => $pago->id, 'cuota_id' => $cuota->id, 'pdf_path' => null]);

    $this->actingAs($cobrador)->get(route('pagos.comprobantes.descargar', $pagoCuota->numero_recibo))->assertNotFound();
});

test('un numero de recibo que no existe devuelve 404', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');

    $this->actingAs($cobrador)->get(route('pagos.comprobantes.descargar', '999999'))->assertNotFound();
});

test('un cobrador puede ver el comprobante inline, no forzado a descargar', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $pagoCuota = pagoCuotaDescargable();

    $response = $this->actingAs($cobrador)->get(route('pagos.comprobantes.ver', $pagoCuota->numero_recibo));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeaderContains('content-disposition', 'inline');
});

test('la pagina de imprimir embebe el pdf en un iframe que se manda a imprimir solo', function () {
    $cobrador = User::factory()->create()->assignRole('cobrador');
    $pagoCuota = pagoCuotaDescargable();

    $response = $this->actingAs($cobrador)->get(route('pagos.comprobantes.imprimir', $pagoCuota->numero_recibo));

    $response->assertOk()
        ->assertSee('<iframe', false)
        ->assertSee('.print()', false)
        ->assertSee(route('pagos.comprobantes.ver', $pagoCuota->numero_recibo), false);
});

test('un profesor no puede ver ni imprimir el comprobante', function () {
    $profesor = User::factory()->create()->assignRole('profesor');
    $pagoCuota = pagoCuotaDescargable();

    $this->actingAs($profesor)->get(route('pagos.comprobantes.ver', $pagoCuota->numero_recibo))->assertForbidden();
    $this->actingAs($profesor)->get(route('pagos.comprobantes.imprimir', $pagoCuota->numero_recibo))->assertForbidden();
});
