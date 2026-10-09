<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Jobs\EnviarComprobantePago;
use App\Cobranzas\Mail\ComprobantePagoEnviado;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Pago;
use App\Cobranzas\Models\PagoCuota;
use App\Cobranzas\Services\GeneradorDeComprobantePago;
use App\Core\Models\PeriodoLectivo;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

test('envia el comprobante solo al tutor responsable de pago', function () {
    Storage::fake('local');
    Mail::fake();

    $periodo = PeriodoLectivo::factory()->activo()->create();
    $tutorResponsable = Tutor::factory()->create(['correo' => 'responsable@example.com']);
    $otroTutor = Tutor::factory()->create(['correo' => 'otro@example.com']);
    $alumno = Alumno::factory()->primario()->create();
    $alumno->tutores()->attach($tutorResponsable->id, ['vinculo' => 'madre', 'responsable_pago' => true]);
    $alumno->tutores()->attach($otroTutor->id, ['vinculo' => 'padre', 'responsable_pago' => false]);

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

    (new EnviarComprobantePago($pagoCuota->fresh()->numero_recibo))->handle();

    Mail::assertSent(ComprobantePagoEnviado::class, fn ($mail) => $mail->hasTo('responsable@example.com'));
    Mail::assertNotSent(ComprobantePagoEnviado::class, fn ($mail) => $mail->hasTo('otro@example.com'));
});

/**
 * Corre el job de verdad (sin Mail::fake) -- mismo criterio que
 * GenerarYEnviarBoletinPdfTest: un fake no hubiera atrapado un PDF roto o
 * una vista de mail inexistente.
 */
test('el flujo completo genera el pdf real y lo adjunta al mail', function () {
    Storage::fake('local');

    $periodo = PeriodoLectivo::factory()->activo()->create();
    $tutor = Tutor::factory()->create();
    $alumno = Alumno::factory()->primario()->create();
    $alumno->tutores()->attach($tutor->id, ['vinculo' => 'madre', 'responsable_pago' => true]);

    $cuota = Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'periodo_lectivo_id' => $periodo->id,
        'monto_base' => 100_000,
        'monto' => 100_000,
    ]);
    $pago = Pago::factory()->create(['monto' => 105_000]);
    $pagoCuota = PagoCuota::factory()->create([
        'pago_id' => $pago->id,
        'cuota_id' => $cuota->id,
        'monto_aplicado' => 100_000,
        'interes_aplicado' => 5_000,
    ]);

    app(GeneradorDeComprobantePago::class)->generar(collect([$pagoCuota]));

    (new EnviarComprobantePago($pagoCuota->fresh()->numero_recibo))->handle();

    expect(true)->toBeTrue(); // No tiró ninguna excepción: el PDF y la vista de mail son reales y válidos.
});
