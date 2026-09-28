<?php

namespace App\Cobranzas\Livewire\Pagos;

use App\Cobranzas\Models\Pago;
use App\Core\Models\PeriodoLectivo;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Listado extends Component
{
    use WithPagination;

    public string $busqueda = '';

    public ?int $pagoAAnularId = null;

    public string $motivoAnulacion = '';

    /**
     * Filtros del PDF -- independientes de $busqueda (que filtra la tabla
     * en pantalla). Se pueden combinar fechas y período, o dejar todo vacío
     * para incluir todos los pagos.
     */
    public string $pdfDesde = '';

    public string $pdfHasta = '';

    public string $pdfPeriodoLectivoId = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Pago::class);
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function prepararAnulacion(int $pagoId): void
    {
        $pago = Pago::findOrFail($pagoId);
        $this->authorize('anular', $pago);

        $this->pagoAAnularId = $pagoId;
        $this->motivoAnulacion = '';
        $this->dispatch('open-modal', name: 'anular-pago');
    }

    public function anular(): void
    {
        $pago = Pago::findOrFail($this->pagoAAnularId);
        $this->authorize('anular', $pago);

        $this->validate([
            'motivoAnulacion' => ['required', 'string', 'max:255'],
        ]);

        $pago->anular($this->motivoAnulacion, auth()->user());

        $this->dispatch('close-modal', name: 'anular-pago');
        $this->reset(['pagoAAnularId', 'motivoAnulacion']);
        session()->flash('mensaje', 'Pago anulado correctamente.');
    }

    public function render()
    {
        $pagos = Pago::query()
            ->with('tutor')
            ->when($this->busqueda, function ($query) {
                $query->where(function ($subquery) {
                    $subquery->where('numero_recibo', 'like', "%{$this->busqueda}%")
                        ->orWhereHas('tutor', fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%"))
                        ->orWhereHas('tutor.alumnos', fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%"));
                });
            })
            ->latest('fecha')
            ->paginate(15);

        return view('livewire.cobranzas.pagos.listado', [
            'pagos' => $pagos,
            'periodos' => PeriodoLectivo::orderByDesc('fecha_inicio')->get(),
        ]);
    }
}
