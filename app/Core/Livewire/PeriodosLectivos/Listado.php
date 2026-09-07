<?php

namespace App\Core\Livewire\PeriodosLectivos;

use App\Core\Models\PeriodoLectivo;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Listado extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', PeriodoLectivo::class);
    }

    public function activar(int $periodoLectivoId): void
    {
        $periodo = PeriodoLectivo::findOrFail($periodoLectivoId);
        $this->authorize('update', $periodo);

        $periodo->activar();

        session()->flash('mensaje', "Período {$periodo->nombre} activado.");
    }

    public function render()
    {
        return view('livewire.core.periodos-lectivos.listado', [
            'periodos' => PeriodoLectivo::orderByDesc('fecha_inicio')->get(),
        ]);
    }
}
