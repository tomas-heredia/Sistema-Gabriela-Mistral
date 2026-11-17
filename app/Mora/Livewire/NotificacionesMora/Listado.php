<?php

namespace App\Mora\Livewire\NotificacionesMora;

use App\Alumnos\Models\Tutor;
use App\Cobranzas\Models\Cuota;
use App\Cobranzas\Models\Enums\EstadoCuota;
use App\Mora\Models\Enums\EstadoNotificacion;
use App\Mora\Models\NotificacionMora;
use App\Mora\Services\CalculadorDeDeuda;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A diferencia de antes, ya no lee `notificaciones_mora` (esa tabla sigue
 * existiendo tal cual, es la interfaz mínima hacia n8n -- ver CLAUDE.md
 * regla 4, y mora:generar-avisos la sigue alimentando para los avisos por
 * WhatsApp/mail). Esta pantalla calcula la deuda en el momento a partir de
 * las cuotas -- el cliente pidió poder ver a los tutores en mora "en todo
 * momento", sin depender de que el comando diario ya haya corrido.
 */
#[Layout('layouts.app')]
class Listado extends Component
{
    use WithPagination;

    public string $busqueda = '';

    public string $desde = '';

    public string $hasta = '';

    public function mount(): void
    {
        $this->authorize('viewAny', NotificacionMora::class);
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatedDesde(): void
    {
        $this->resetPage();
    }

    public function updatedHasta(): void
    {
        $this->resetPage();
    }

    /**
     * Anula (no borra -- queda auditada vía LogsActivity) toda la deuda
     * vencida de un tutor: todas las cuotas pendientes/parciales con
     * vencimiento pasado de los alumnos donde es responsable de pago,
     * misma definición de "deuda" que usa CalculadorDeDeuda (las cuotas
     * futuras, aún no vencidas, no se tocan). También cancela cualquier
     * aviso de mora que ya estuviera en cola para ese tutor, para que no le
     * llegue un aviso de una deuda que ya no existe.
     */
    public function eliminarDeuda(int $tutorId): void
    {
        $this->authorize('anularDeuda', Cuota::class);

        $tutor = Tutor::findOrFail($tutorId);

        DB::transaction(function () use ($tutor) {
            $alumnoIds = $tutor->alumnos()->wherePivot('responsable_pago', true)->pluck('alumnos.id');

            Cuota::query()
                ->whereIn('alumno_id', $alumnoIds)
                ->whereIn('estado', [EstadoCuota::Pendiente, EstadoCuota::Parcial])
                ->where('fecha_vencimiento', '<', today())
                ->get()
                ->each(fn (Cuota $cuota) => $cuota->update(['estado' => EstadoCuota::Anulada]));

            NotificacionMora::query()
                ->where('tutor_id', $tutor->id)
                ->where('estado', EstadoNotificacion::Pendiente)
                ->update(['estado' => EstadoNotificacion::Cancelado]);
        });

        $this->resetPage();
        session()->flash('mensaje', 'Deuda del tutor eliminada.');
    }

    public function render(CalculadorDeDeuda $calculador)
    {
        $todos = $calculador->tutoresEnMora(
            $this->desde ?: null,
            $this->hasta ?: null,
            $this->busqueda,
        );

        $porPagina = 15;
        $pagina = $this->getPage();

        $morosos = new LengthAwarePaginator(
            $todos->slice(($pagina - 1) * $porPagina, $porPagina)->values(),
            $todos->count(),
            $porPagina,
            $pagina,
            ['path' => request()->url(), 'pageName' => 'page'],
        );

        return view('livewire.mora.notificaciones-mora.listado', [
            'morosos' => $morosos,
        ]);
    }
}
