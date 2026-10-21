<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\TipoCuota;
use App\Mora\Jobs\GenerarYEnviarResumenDeDeuda;
use App\Mora\Mail\ResumenDeDeudaEnviado;
use App\Mora\Models\ResumenDeuda;
use App\Mora\Services\CalculadorDeDeuda;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function tutorConCuotaVencida(): array
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

    return [$tutor, $alumno];
}

test('genera el pdf, lo manda por mail y deja el registro de auditoria', function () {
    Storage::fake('local');
    Mail::fake();

    [$tutor] = tutorConCuotaVencida();

    (new GenerarYEnviarResumenDeDeuda($tutor, '2026-10'))->handle(app(CalculadorDeDeuda::class));

    Mail::assertSent(ResumenDeDeudaEnviado::class, fn ($mail) => $mail->hasTo($tutor->correo));

    $registro = ResumenDeuda::where('tutor_id', $tutor->id)->where('periodo', '2026-10')->first();
    expect($registro)->not->toBeNull()
        ->and($registro->monto_adeudado)->toBe(100_000);

    Storage::disk('local')->assertExists($registro->pdf_path);
});

test('no manda nada si la deuda ya se salda antes de correr el job', function () {
    Storage::fake('local');
    Mail::fake();

    [$tutor] = tutorConCuotaVencida();

    Cuota::whereHas('alumno.tutores', fn ($q) => $q->where('tutores.id', $tutor->id))
        ->get()
        ->each(fn (Cuota $cuota) => $cuota->update(['estado' => EstadoCuota::Pagada]));

    (new GenerarYEnviarResumenDeDeuda($tutor, '2026-10'))->handle(app(CalculadorDeDeuda::class));

    Mail::assertNothingSent();
    expect(ResumenDeuda::count())->toBe(0);
});

test('es idempotente: no reenvia si ya existe un registro para el mismo tutor y periodo', function () {
    Storage::fake('local');
    Mail::fake();

    [$tutor] = tutorConCuotaVencida();

    ResumenDeuda::factory()->create([
        'tutor_id' => $tutor->id,
        'periodo' => '2026-10',
    ]);

    (new GenerarYEnviarResumenDeDeuda($tutor, '2026-10'))->handle(app(CalculadorDeDeuda::class));

    Mail::assertNothingSent();
    expect(ResumenDeuda::where('tutor_id', $tutor->id)->count())->toBe(1);
});
