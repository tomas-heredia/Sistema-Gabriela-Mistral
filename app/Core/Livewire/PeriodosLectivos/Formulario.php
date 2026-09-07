<?php

namespace App\Core\Livewire\PeriodosLectivos;

use App\Core\Models\PeriodoLectivo;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Formulario extends Component
{
    public ?PeriodoLectivo $periodoLectivo = null;

    public string $nombre = '';

    public string $fecha_inicio = '';

    public string $fecha_fin = '';

    public string $descuento_hermanos_pct = '0';

    public function mount(?PeriodoLectivo $periodoLectivo = null): void
    {
        if ($periodoLectivo?->exists) {
            $this->authorize('update', $periodoLectivo);

            $this->periodoLectivo = $periodoLectivo;
            $this->nombre = $periodoLectivo->nombre;
            $this->fecha_inicio = $periodoLectivo->fecha_inicio->format('Y-m-d');
            $this->fecha_fin = $periodoLectivo->fecha_fin->format('Y-m-d');
            $this->descuento_hermanos_pct = (string) $periodoLectivo->descuento_hermanos_pct;
        } else {
            $this->authorize('create', PeriodoLectivo::class);
        }
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            'descuento_hermanos_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
            'descuento_hermanos_pct' => 'descuento por hermanos',
        ];
    }

    public function guardar(): void
    {
        $datos = $this->validate();

        if ($this->periodoLectivo) {
            $this->periodoLectivo->update($datos);
            session()->flash('mensaje', 'Período actualizado correctamente.');
        } else {
            PeriodoLectivo::create($datos);
            session()->flash('mensaje', 'Período creado correctamente.');
        }

        $this->redirectRoute('periodos.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.core.periodos-lectivos.formulario');
    }
}
