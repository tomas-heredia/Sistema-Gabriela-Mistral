<?php

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Cobranzas\Models\Enums\TipoCuota;
use App\Mora\Models\Enums\EstadoNotificacion;
use App\Mora\Models\NotificacionMora;

function tutorConDeuda(int $mesesVencidosImpagos = 2): Tutor
{
    $tutor = Tutor::factory()->create();
    $alumno = Alumno::factory()->create();
    $alumno->tutores()->attach($tutor, ['vinculo' => 'madre', 'responsable_pago' => true]);

    for ($i = 0; $i < $mesesVencidosImpagos; $i++) {
        Cuota::factory()->create([
            'alumno_id' => $alumno->id,
            'tipo' => TipoCuota::Mensualidad,
            'mes' => $i + 1,
            'monto' => 100_000,
            'estado' => EstadoCuota::Pendiente,
            'fecha_vencimiento' => now()->subMonths($i + 1),
        ]);
    }

    return $tutor;
}

test('genera un aviso con el monto y los meses adeudados correctos', function () {
    $tutor = tutorConDeuda(mesesVencidosImpagos: 2);

    $this->artisan('mora:generar-avisos')->assertSuccessful();

    $aviso = NotificacionMora::where('tutor_id', $tutor->id)->first();

    expect($aviso)->not->toBeNull()
        ->and($aviso->monto_adeudado)->toBe(200_000)
        ->and($aviso->meses_adeudados)->toBe(2)
        ->and($aviso->estado)->toBe(EstadoNotificacion::Pendiente);
});

test('no genera aviso para un tutor sin deuda', function () {
    Tutor::factory()->create();

    $this->artisan('mora:generar-avisos')->assertSuccessful();

    expect(NotificacionMora::count())->toBe(0);
});

test('no duplica un aviso si ya hay uno pendiente sin consumir', function () {
    $tutor = tutorConDeuda();

    $this->artisan('mora:generar-avisos')->assertSuccessful();
    expect(NotificacionMora::where('tutor_id', $tutor->id)->count())->toBe(1);

    $this->artisan('mora:generar-avisos')->assertSuccessful();
    expect(NotificacionMora::where('tutor_id', $tutor->id)->count())->toBe(1);
});

test('cancela un aviso pendiente si la deuda ya se salda antes de ser consumido', function () {
    $tutor = tutorConDeuda();
    $this->artisan('mora:generar-avisos')->assertSuccessful();

    // Se saldan todas las cuotas del tutor.
    Cuota::whereHas('alumno.tutores', fn ($q) => $q->where('tutores.id', $tutor->id))
        ->get()
        ->each(fn (Cuota $cuota) => $cuota->update(['estado' => EstadoCuota::Pagada]));

    $this->artisan('mora:generar-avisos')->assertSuccessful();

    $aviso = NotificacionMora::where('tutor_id', $tutor->id)->first();
    expect($aviso->estado)->toBe(EstadoNotificacion::Cancelado);
});

test('no regenera un aviso antes de 10 dias desde el ultimo, aunque ya no este pendiente', function () {
    $tutor = tutorConDeuda();

    NotificacionMora::factory()->create([
        'tutor_id' => $tutor->id,
        'estado' => EstadoNotificacion::Enviado,
        'fecha' => now()->subDays(5),
    ]);

    $this->artisan('mora:generar-avisos')->assertSuccessful();

    expect(NotificacionMora::where('tutor_id', $tutor->id)->count())->toBe(1);
});

test('regenera un aviso pasados los 10 dias del ultimo, si la deuda persiste', function () {
    $tutor = tutorConDeuda();

    NotificacionMora::factory()->create([
        'tutor_id' => $tutor->id,
        'estado' => EstadoNotificacion::Enviado,
        'fecha' => now()->subDays(11),
    ]);

    $this->artisan('mora:generar-avisos')->assertSuccessful();

    expect(NotificacionMora::where('tutor_id', $tutor->id)->count())->toBe(2);
});
