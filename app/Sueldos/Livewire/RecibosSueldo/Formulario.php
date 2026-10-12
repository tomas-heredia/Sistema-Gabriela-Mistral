<?php

namespace App\Sueldos\Livewire\RecibosSueldo;

use App\Core\Models\User;
use App\Sueldos\Models\ReciboSueldo;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Formulario extends Component
{
    use WithFileUploads;

    public string $profesor_id = '';

    public string $periodo = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $archivos = [];

    public function mount(): void
    {
        $this->authorize('create', ReciboSueldo::class);
    }

    protected function rules(): array
    {
        return [
            'profesor_id' => ['required', Rule::exists('users', 'id')],
            'periodo' => [
                'required',
                'date_format:Y-m',
                Rule::unique('recibos_sueldo', 'periodo')->where(fn ($query) => $query->where('profesor_id', $this->profesor_id)),
            ],
            'archivos' => ['required', 'array', 'min:1'],
            'archivos.*' => ['file', 'mimes:pdf', 'max:5120'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'profesor_id' => 'profesor',
            'periodo' => 'período',
            'archivos' => 'archivos',
            'archivos.*' => 'archivo',
        ];
    }

    public function guardar(): void
    {
        $datos = $this->validate();

        $recibo = ReciboSueldo::create([
            'profesor_id' => $datos['profesor_id'],
            'periodo' => $datos['periodo'],
            'cargado_por_id' => auth()->id(),
            'fecha_carga' => now(),
        ]);

        foreach ($datos['archivos'] as $archivo) {
            $ruta = $archivo->store("recibos-sueldo/{$datos['profesor_id']}/{$datos['periodo']}", 'local');

            $recibo->archivos()->create(['archivo' => $ruta]);
        }

        session()->flash('mensaje', 'Recibo cargado correctamente.');
        $this->redirectRoute('recibos-sueldo.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.sueldos.recibos-sueldo.formulario', [
            'profesores' => User::role('profesor')->orderBy('name')->get(),
        ]);
    }
}
