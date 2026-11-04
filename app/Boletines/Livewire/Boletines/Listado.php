<?php

namespace App\Boletines\Livewire\Boletines;

use App\Alumnos\Models\Alumno;
use App\Alumnos\Models\Enums\Nivel;
use App\Boletines\Exceptions\PlantillaNoEncontradaException;
use App\Boletines\Models\Boletin;
use App\Boletines\Models\Enums\EstadoBoletin;
use App\Boletines\Services\CreadorDeBoletines;
use App\Core\Models\PeriodoLectivo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function eliminar(int $boletinId): void
    {
        $boletin = Boletin::findOrFail($boletinId);
        $this->authorize('delete', $boletin);

        $boletin->eliminarConTrimestres(auth()->user());

        session()->flash('mensaje', 'Libreta eliminada correctamente.');
    }

    /**
     * Un boletín "en curso" (ej. esperando la recuperación de diciembre o
     * febrero) tiene que seguir apareciendo aunque ya haya un período
     * lectivo nuevo activo -- si solo mostráramos el período activo, la
     * libreta de un alumno que sigue en PAeIP/ICAP desaparecería del
     * listado justo cuando el ciclo lectivo cambia, sin forma de terminarla.
     */
    private function scopeVisible(Builder|HasMany $query, ?PeriodoLectivo $periodoActivo): Builder|HasMany
    {
        return $query->where(function ($subquery) use ($periodoActivo) {
            $subquery->where('estado', EstadoBoletin::EnCurso);

            if ($periodoActivo) {
                $subquery->orWhere('periodo_lectivo_id', $periodoActivo->id);
            }
        });
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
                ->with(['boletines' => function ($subquery) use ($periodoActivo) {
                    // Si el alumno tiene dos libretas visibles a la vez (una vieja
                    // todavía en curso por una recuperación pendiente, y la del
                    // período activo), se prioriza mostrar la que está en curso.
                    $this->scopeVisible($subquery, $periodoActivo)
                        ->orderByRaw("estado = 'en_curso' desc")
                        ->with('trimestres');
                }])
                ->orderBy('nombre')
                ->paginate(15);

            return view('livewire.boletines.boletines.listado', [
                'boletines' => null,
                'alumnosBuscados' => $alumnosBuscados,
                'periodoActivo' => $periodoActivo,
            ]);
        }

        $boletines = $this->scopeVisible(
            Boletin::query()->with(['alumno', 'trimestres']),
            $periodoActivo,
        )
            ->latest('id')
            ->paginate(15);

        return view('livewire.boletines.boletines.listado', [
            'boletines' => $boletines,
            'alumnosBuscados' => null,
            'periodoActivo' => $periodoActivo,
        ]);
    }
}
