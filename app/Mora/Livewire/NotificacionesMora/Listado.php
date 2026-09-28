<?php

namespace App\Mora\Livewire\NotificacionesMora;

use App\Mora\Models\NotificacionMora;
use App\Mora\Services\CalculadorDeDeuda;
use Illuminate\Pagination\LengthAwarePaginator;
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
