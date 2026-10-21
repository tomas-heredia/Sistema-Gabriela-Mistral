<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\TipoCuota;
use App\Mora\Jobs\GenerarYEnviarResumenDeDeuda;
use Illuminate\Support\Facades\Bus;

function tutorConDeudaParaResumen(): Tutor
{
    $tutor = Tutor::factory()->create();
    $alumno = Alumno::factory()->create();
    $alumno->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);

    Cuota::factory()->create([
        'alumno_id' => $alumno->id,
        'tipo' => TipoCuota::Mensualidad,
        'mes' => 3,
        'monto' => 100_000,
        'estado' => EstadoCuota::Pendiente,
        'fecha_vencimiento' => now()->subMonth(),
    ]);

    return $tutor;
}

test('no encola nada antes del dia 10 del mes', function () {
    $this->travelTo(now()->startOfMonth()->addDays(4));
    Bus::fake();

    tutorConDeudaParaResumen();

    $this->artisan('mora:generar-resumenes')->assertSuccessful();

    Bus::assertNotDispatched(GenerarYEnviarResumenDeDeuda::class);
});

test('encola un resumen por cada tutor en mora a partir del dia 10', function () {
    $this->travelTo(now()->startOfMonth()->addDays(9));
    Bus::fake();

    $tutor = tutorConDeudaParaResumen();
    Tutor::factory()->create(); // sin deuda, no debe encolarse

    $this->artisan('mora:generar-resumenes')->assertSuccessful();

    Bus::assertDispatched(
        GenerarYEnviarResumenDeDeuda::class,
        fn ($job) => $job->tutor->is($tutor) && $job->periodo === now()->format('Y-m')
    );
    Bus::assertDispatchedTimes(GenerarYEnviarResumenDeDeuda::class, 1);
});
