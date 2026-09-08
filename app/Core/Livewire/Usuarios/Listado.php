<?php

namespace App\Core\Livewire\Usuarios;

use App\Core\Models\User;
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
        $this->authorize('viewAny', User::class);
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function eliminar(int $usuarioId): void
    {
        $usuario = User::findOrFail($usuarioId);
        $this->authorize('delete', $usuario);

        try {
            $usuario->delete();
            session()->flash('mensaje', 'Usuario eliminado correctamente.');
        } catch (QueryException) {
            session()->flash('error', 'No se pudo eliminar: este usuario tiene registros asociados (pagos, boletines, recibos, etc.).');
        }
    }

    public function render()
    {
        $usuarios = User::query()
            ->with('roles')
            ->when($this->busqueda, function ($query) {
                $query->where(function ($subquery) {
                    $subquery->where('name', 'like', "%{$this->busqueda}%")
                        ->orWhere('email', 'like', "%{$this->busqueda}%");
                });
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.core.usuarios.listado', [
            'usuarios' => $usuarios,
        ]);
    }
}
