<?php

namespace App\Alumnos\Livewire\Tutores;

use App\Alumnos\Models\Tutor;
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
        $this->authorize('viewAny', Tutor::class);
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    /**
     * "Eliminar" desactiva, no borra -- mismo criterio que Alumno (ver
     * Alumnos\Listado::eliminar()).
     */
    public function eliminar(Tutor $tutor): void
    {
        $this->authorize('delete', $tutor);

        $tutor->update(['activo' => false]);
        session()->flash('mensaje', 'Tutor desactivado correctamente.');
    }

    public function render()
    {
        $tutores = Tutor::query()
            ->when($this->busqueda, function ($query) {
                $query->where(function ($subquery) {
                    $subquery->where('nombre', 'like', "%{$this->busqueda}%")
                        ->orWhere('dni', 'like', "%{$this->busqueda}%");
                });
            })
            ->orderBy('nombre')
            ->paginate(15);

        return view('livewire.alumnos.tutores.listado', ['tutores' => $tutores]);
    }
}
