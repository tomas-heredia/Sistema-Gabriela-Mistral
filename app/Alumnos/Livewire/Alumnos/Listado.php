<?php

namespace App\Alumnos\Livewire\Alumnos;

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Enums\Nivel;
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

    /**
     * "Eliminar" desactiva, no borra -- un alumno tiene cuotas, pagos y
     * libretas que son registros históricos del colegio, no algo que deba
     * desaparecer porque se fue. Se puede reactivar editándolo.
     */
    public function eliminar(Alumno $alumno): void
    {
        $this->authorize('delete', $alumno);

        $alumno->update(['activo' => false]);
        session()->flash('mensaje', 'Alumno desactivado correctamente.');
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
