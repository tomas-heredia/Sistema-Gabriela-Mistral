<?php

namespace App\Core\Livewire\Usuarios;

use App\Core\Models\User;
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

    /**
     * "Eliminar" desactiva, no borra -- un usuario queda referenciado desde
     * pagos, boletines y recibos que cargó (quién hizo qué), así que no
     * tiene sentido borrarlo del todo. Desactivado, no puede loguearse
     * (ver LoginForm::authenticate()) pero sigue apareciendo donde ya
     * estaba asociado.
     */
    public function eliminar(int $usuarioId): void
    {
        $usuario = User::findOrFail($usuarioId);
        $this->authorize('delete', $usuario);

        $usuario->update(['activo' => false]);
        session()->flash('mensaje', 'Usuario desactivado correctamente.');
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
