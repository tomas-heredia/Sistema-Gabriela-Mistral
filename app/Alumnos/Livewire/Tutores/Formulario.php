<?php

namespace App\Alumnos\Livewire\Tutores;

use App\Alumnos\Models\Tutor;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Formulario extends Component
{
    public ?Tutor $tutor = null;

    public string $nombre = '';

    public string $dni = '';

    public string $domicilio = '';

    public string $telefono = '';

    public ?string $correo = null;

    public function mount(?Tutor $tutor = null): void
    {
        if ($tutor?->exists) {
            $this->authorize('update', $tutor);

            $this->tutor = $tutor;
            $this->nombre = $tutor->nombre;
            $this->dni = $tutor->dni;
            $this->domicilio = $tutor->domicilio;
            $this->telefono = $tutor->telefono;
            $this->correo = $tutor->correo;
        } else {
            $this->authorize('create', Tutor::class);
        }
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'dni' => ['required', 'string', 'max:20', Rule::unique('tutores', 'dni')->ignore($this->tutor?->id)],
            'domicilio' => ['required', 'string', 'max:255'],
            'telefono' => ['required', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:255'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre',
            'dni' => 'DNI',
            'domicilio' => 'domicilio',
            'telefono' => 'teléfono',
            'correo' => 'correo',
        ];
    }

    public function guardar(): void
    {
        $datos = $this->validate();

        if ($this->tutor) {
            $this->tutor->update($datos);
        } else {
            Tutor::create($datos);
        }

        session()->flash('mensaje', 'Tutor guardado correctamente.');
        $this->redirectRoute('tutores.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.alumnos.tutores.formulario');
    }
}
