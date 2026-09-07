<?php

namespace App\Mora\Livewire\NotificacionesMora;

use App\Mora\Models\Enums\EstadoNotificacion;
use App\Mora\Models\NotificacionMora;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Solo lectura: `notificaciones_mora` es una interfaz mínima hacia n8n (ver
 * CLAUDE.md regla 4) — es n8n quien la actualiza (estado, fecha_envio,
 * intentos) al mandar el aviso de verdad. Esta pantalla es puramente de
 * visibilidad sobre lo que ya generó `mora:generar-avisos`.
 */
#[Layout('layouts.app')]
class Listado extends Component
{
    use WithPagination;

    public string $busqueda = '';

    public string $estado = '';

    public function mount(): void
    {
        $this->authorize('viewAny', NotificacionMora::class);
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatedEstado(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $notificaciones = NotificacionMora::query()
            ->with('tutor')
            ->when($this->busqueda, function ($query) {
                $query->whereHas('tutor', function ($subquery) {
                    $subquery->where('nombre', 'like', "%{$this->busqueda}%")
                        ->orWhere('dni', 'like', "%{$this->busqueda}%");
                });
            })
            ->when($this->estado, fn ($query) => $query->where('estado', $this->estado))
            ->latest('fecha')
            ->paginate(15);

        return view('livewire.mora.notificaciones-mora.listado', [
            'notificaciones' => $notificaciones,
            'estados' => EstadoNotificacion::cases(),
        ]);
    }
}
