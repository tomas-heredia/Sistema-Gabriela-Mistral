<?php

namespace App\Boletines\Livewire\Boletines;

use App\Boletines\Models\Boletin;
use App\Core\Models\PeriodoLectivo;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Listado extends Component
{
    use WithPagination;

    public string $busqueda = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Boletin::class);
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $periodoActivo = PeriodoLectivo::where('activo', true)->first();

        $boletines = Boletin::query()
            ->with(['alumno', 'trimestres'])
            ->when($periodoActivo, fn ($query) => $query->where('periodo_lectivo_id', $periodoActivo->id))
            ->when(! $periodoActivo, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($this->busqueda, function ($query) {
                $query->whereHas('alumno', function ($subquery) {
                    $subquery->where('nombre', 'like', "%{$this->busqueda}%")
                        ->orWhere('dni', 'like', "%{$this->busqueda}%");
                });
            })
            ->latest('id')
            ->paginate(15);

        return view('livewire.boletines.boletines.listado', [
            'boletines' => $boletines,
            'periodoActivo' => $periodoActivo,
        ]);
    }
}
