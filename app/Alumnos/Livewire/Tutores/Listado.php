<?php

namespace App\Alumnos\Livewire\Tutores;

use App\Alumnos\Models\Tutor;
use Illuminate\Database\QueryException;
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

    public function eliminar(Tutor $tutor): void
    {
        $this->authorize('delete', $tutor);

        try {
            $tutor->delete();
            session()->flash('mensaje', 'Tutor eliminado correctamente.');
        } catch (QueryException) {
            session()->flash('error', 'No se pudo eliminar: este tutor tiene pagos u otros registros asociados.');
        }
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
