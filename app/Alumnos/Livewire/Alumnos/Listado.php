<?php

namespace App\Alumnos\Livewire\Alumnos;

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Enums\Nivel;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Listado extends Component
{
    use WithPagination;

    public string $busqueda = '';

    public string $nivel = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Alumno::class);
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatedNivel(): void
    {
        $this->resetPage();
    }

    public function eliminar(Alumno $alumno): void
    {
        $this->authorize('delete', $alumno);

        try {
            $alumno->delete();
            session()->flash('mensaje', 'Alumno eliminado correctamente.');
        } catch (QueryException) {
            session()->flash('error', 'No se pudo eliminar: este alumno tiene cuotas, boletines u otros registros asociados.');
        }
    }

    public function render()
    {
        $alumnos = Alumno::query()
            ->when($this->busqueda, function ($query) {
                $query->where(function ($subquery) {
                    $subquery->where('nombre', 'like', "%{$this->busqueda}%")
                        ->orWhere('dni', 'like', "%{$this->busqueda}%");
                });
            })
            ->when($this->nivel, fn ($query) => $query->where('nivel', $this->nivel))
            ->orderBy('nombre')
            ->paginate(15);

        return view('livewire.alumnos.alumnos.listado', [
            'alumnos' => $alumnos,
            'niveles' => Nivel::cases(),
        ]);
    }
}
