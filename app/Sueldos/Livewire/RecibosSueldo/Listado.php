<?php

namespace App\Sueldos\Livewire\RecibosSueldo;

use App\Sueldos\Models\ReciboSueldo;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Un solo componente para los dos roles que pueden verlo: la Policy ya
 * distingue "puede listar" (administrador y profesor) de "a qué filas
 * tiene acceso" -- acá se aplica ese filtro en la consulta, como ya lo
 * documenta ReciboSueldoPolicy::viewAny().
 */
#[Layout('layouts.app')]
class Listado extends Component
{
    use WithPagination;

    public string $busqueda = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ReciboSueldo::class);
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function eliminar(int $reciboId): void
    {
        $recibo = ReciboSueldo::with('archivos')->findOrFail($reciboId);
        $this->authorize('delete', $recibo);

        foreach ($recibo->archivos as $archivo) {
            Storage::disk('local')->delete($archivo->archivo);
        }

        $recibo->delete();

        session()->flash('mensaje', 'Recibo eliminado correctamente.');
    }

    public function render()
    {
        // "Gestionar" decide qué ve y el botón de "Nuevo recibo" (administrador
        // siempre, cobrador solo mientras está en la red del colegio -- ver
        // ReciboSueldoPolicy::gestionar). "Eliminar" queda aparte, exclusivo
        // de administrador, nunca se le abrió a cobrador.
        $puedeGestionar = auth()->user()->can('gestionar', ReciboSueldo::class);
        $esAdministrador = auth()->user()->hasRole('administrador');

        $recibos = ReciboSueldo::query()
            ->with('profesor', 'archivos')
            ->when(! $puedeGestionar, fn ($query) => $query->where('profesor_id', auth()->id()))
            ->when($this->busqueda, function ($query) {
                $query->whereHas('profesor', function ($subquery) {
                    $subquery->where('name', 'like', "%{$this->busqueda}%")
                        ->orWhere('email', 'like', "%{$this->busqueda}%");
                });
            })
            ->latest('fecha_carga')
            ->paginate(15);

        return view('livewire.sueldos.recibos-sueldo.listado', [
            'recibos' => $recibos,
            'puedeGestionar' => $puedeGestionar,
            'esAdministrador' => $esAdministrador,
        ]);
    }
}
