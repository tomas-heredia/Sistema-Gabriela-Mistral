<?php

namespace App\Mora\Console\Commands;

use App\Alumnos\Models\Tutor;
use App\Mora\Models\Enums\EstadoNotificacion;
use App\Mora\Models\NotificacionMora;
use App\Mora\Services\CalculadorDeDeuda;
use Illuminate\Console\Command;

/**
 * Alimenta la tabla outbox `notificaciones_mora` que consume n8n.
 *
 * Se programa para correr a diario (ver routes/console.php), pero el
 * intervalo real de "cada 10 días" lo garantiza esta lógica, no el cron:
 * así sobrevive a que el scheduler se caiga un día y no depende de que un
 * cron exprese "cada 10 días" de forma limpia entre meses.
 */
class GenerarAvisosMora extends Command
{
    protected $signature = 'mora:generar-avisos';

    protected $description = 'Genera los avisos de mora pendientes de envío para n8n';

    private const DIAS_ENTRE_AVISOS = 10;

    public function handle(CalculadorDeDeuda $calculador): int
    {
        $this->cancelarPendientesSinDeuda($calculador);
        $this->generarNuevasNotificaciones($calculador);

        return self::SUCCESS;
    }

    /**
     * 1. Si la deuda ya se saldó, cancela cualquier aviso pendiente de
     * enviar — evita que n8n mande un aviso de una deuda que ya no existe.
     */
    private function cancelarPendientesSinDeuda(CalculadorDeDeuda $calculador): void
    {
        NotificacionMora::query()
            ->where('estado', EstadoNotificacion::Pendiente)
            ->with('tutor')
            ->get()
            ->each(function (NotificacionMora $notificacion) use ($calculador) {
                if ($calculador->calcular($notificacion->tutor)['monto_adeudado'] <= 0) {
                    $notificacion->update(['estado' => EstadoNotificacion::Cancelado]);
                }
            });
    }

    /**
     * 2. Para cada tutor con deuda: si no tiene un aviso pendiente de
     * consumir, y (nunca tuvo uno o el último fue hace más de 10 días),
     * genera uno nuevo.
     */
    private function generarNuevasNotificaciones(CalculadorDeDeuda $calculador): void
    {
        Tutor::query()->chunk(100, function ($tutores) use ($calculador) {
            foreach ($tutores as $tutor) {
                $deuda = $calculador->calcular($tutor);

                if ($deuda['monto_adeudado'] <= 0) {
                    continue;
                }

                if (! $this->correspondeGenerarAviso($tutor)) {
                    continue;
                }

                NotificacionMora::create([
                    'tutor_id' => $tutor->id,
                    'monto_adeudado' => $deuda['monto_adeudado'],
                    'meses_adeudados' => $deuda['meses_adeudados'],
                    'estado' => EstadoNotificacion::Pendiente,
                    'fecha' => today(),
                ]);
            }
        });
    }

    private function correspondeGenerarAviso(Tutor $tutor): bool
    {
        $tienePendiente = NotificacionMora::query()
            ->where('tutor_id', $tutor->id)
            ->where('estado', EstadoNotificacion::Pendiente)
            ->exists();

        if ($tienePendiente) {
            return false;
        }

        $ultima = NotificacionMora::query()
            ->where('tutor_id', $tutor->id)
            ->latest('fecha')
            ->first();

        return $ultima === null || $ultima->fecha->lte(today()->subDays(self::DIAS_ENTRE_AVISOS));
    }
}
