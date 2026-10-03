<?php

namespace App\Boletines\Livewire\Boletines;

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Enums\Nivel;
use App\Boletines\Exceptions\PlantillaNoEncontradaException;
use App\Boletines\Models\Boletin;
use App\Boletines\Services\CreadorDeBoletines;
use App\Core\Models\PeriodoLectivo;
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
        $this->authorize('viewAny', Boletin::class);
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    /**
     * Crea la libreta de un alumno encontrado por búsqueda que todavía no
     * tiene una para el período activo — misma lógica que
     * Alumnos\Formulario::generarBoletin(), para no duplicar las reglas de
     * negocio (plantilla por nivel, nivel inicial sin libreta, una sola vez
     * por período).
     */
    public function crearLibreta(int $alumnoId, CreadorDeBoletines $creador): void
    {
        $this->authorize('create', Boletin::class);

        $alumno = Alumno::findOrFail($alumnoId);
        $periodo = PeriodoLectivo::where('activo', true)->first();

        if (! $periodo || $alumno->nivel === Nivel::Inicial) {
            return;
        }

        $yaTieneLibreta = Boletin::where('alumno_id', $alumno->id)
            ->where('periodo_lectivo_id', $periodo->id)
            ->exists();

        if ($yaTieneLibreta) {
            return;
        }

        try {
            $creador->crear($alumno, $periodo);
            session()->flash('mensaje', 'Libreta generada correctamente.');
        } catch (PlantillaNoEncontradaException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $periodoActivo = PeriodoLectivo::where('activo', true)->first();

        if ($this->busqueda !== '') {
            $alumnosBuscados = Alumno::query()
                ->where(function ($query) {
                    $query->where('nombre', 'like', "%{$this->busqueda}%")
                        ->orWhere('dni', 'like', "%{$this->busqueda}%");
                })
                ->when($periodoActivo, function ($query) use ($periodoActivo) {
                    $query->with(['boletines' => function ($subquery) use ($periodoActivo) {
                        $subquery->where('periodo_lectivo_id', $periodoActivo->id)->with('trimestres');
                    }]);
                })
                ->orderBy('nombre')
                ->paginate(15);

            return view('livewire.boletines.boletines.listado', [
                'boletines' => null,
                'alumnosBuscados' => $alumnosBuscados,
                'periodoActivo' => $periodoActivo,
            ]);
        }

        $boletines = Boletin::query()
            ->with(['alumno', 'trimestres'])
            ->when($periodoActivo, fn ($query) => $query->where('periodo_lectivo_id', $periodoActivo->id))
            ->when(! $periodoActivo, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest('id')
            ->paginate(15);

        return view('livewire.boletines.boletines.listado', [
            'boletines' => $boletines,
            'alumnosBuscados' => null,
            'periodoActivo' => $periodoActivo,
        ]);
    }
}
