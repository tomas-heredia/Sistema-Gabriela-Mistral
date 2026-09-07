<?php

namespace App\Core\Livewire\PeriodosLectivos;

use App\Alumnos\Models\Enums\Nivel;
use App\Cobranzas\Models\Arancel;
use App\Cobranzas\Models\Enums\TipoCuota;
use App\Core\Models\PeriodoLectivo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Los aranceles (matrícula/mensualidad × primario/secundario, 4 filas fijas
 * por período) se cargan acá mismo, no en una pantalla propia: no tienen
 * identidad ni búsqueda propia, son una matriz de configuración atada 1 a 1
 * a un período. Son obligatorios para no volver a depender del
 * ModelNotFoundException que atrapa Alumnos\Formulario::generarCuotas()
 * cuando falta uno.
 */
#[Layout('layouts.app')]
class Formulario extends Component
{
    public ?PeriodoLectivo $periodoLectivo = null;

    public string $nombre = '';

    public string $fecha_inicio = '';

    public string $fecha_fin = '';

    public string $descuento_hermanos_pct = '0';

    /** @var array<string, string> ["{nivel}_{tipo}" => monto en pesos, como texto] */
    public array $montos = [];

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

        $aranceles = $this->periodoLectivo
            ? Arancel::where('periodo_lectivo_id', $this->periodoLectivo->id)->get()->keyBy(fn ($a) => "{$a->nivel->value}_{$a->tipo->value}")
            : collect();

        foreach (Nivel::cases() as $nivel) {
            foreach (TipoCuota::cases() as $tipo) {
                $clave = "{$nivel->value}_{$tipo->value}";
                $arancel = $aranceles->get($clave);
                $this->montos[$clave] = $arancel ? number_format($arancel->monto / 100, 2, '.', '') : '';
            }
        }
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            'descuento_hermanos_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'montos.*' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function validationAttributes(): array
    {
        $atributos = [
            'nombre' => 'nombre',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
            'descuento_hermanos_pct' => 'descuento por hermanos',
        ];

        foreach (Nivel::cases() as $nivel) {
            foreach (TipoCuota::cases() as $tipo) {
                $atributos["montos.{$nivel->value}_{$tipo->value}"] = "{$tipo->value} ({$nivel->value})";
            }
        }

        return $atributos;
    }

    public function guardar(): void
    {
        $datos = $this->validate();

        DB::transaction(function () use ($datos) {
            if ($this->periodoLectivo) {
                $this->periodoLectivo->update(Arr::except($datos, 'montos'));
            } else {
                $this->periodoLectivo = PeriodoLectivo::create(Arr::except($datos, 'montos'));
            }

            foreach (Nivel::cases() as $nivel) {
                foreach (TipoCuota::cases() as $tipo) {
                    $clave = "{$nivel->value}_{$tipo->value}";

                    Arancel::updateOrCreate(
                        ['periodo_lectivo_id' => $this->periodoLectivo->id, 'nivel' => $nivel, 'tipo' => $tipo],
                        ['monto' => (int) round(((float) $datos['montos'][$clave]) * 100)]
                    );
                }
            }
        });

        session()->flash('mensaje', $this->periodoLectivo->wasRecentlyCreated ? 'Período creado correctamente.' : 'Período actualizado correctamente.');
        $this->redirectRoute('periodos.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.core.periodos-lectivos.formulario', [
            'niveles' => Nivel::cases(),
            'tipos' => TipoCuota::cases(),
        ]);
    }
}
