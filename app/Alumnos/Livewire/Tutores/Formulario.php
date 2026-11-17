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

    public string $correo = '';

    public bool $activo = true;

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
            $this->activo = $tutor->activo;
        } else {
            $this->authorize('create', Tutor::class);
        }
    }

    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'dni' => ['required', 'digits_between:1,20', Rule::unique('tutores', 'dni')->ignore($this->tutorInactivoConMismoDni()?->id ?? $this->tutor?->id)],
            'domicilio' => ['required', 'string', 'max:255'],
            'telefono' => ['required', 'digits_between:1,30'],
            'correo' => ['required', 'email', 'max:255'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * Solo tiene sentido en el alta (editando un tutor existente, el DNI ya
     * es el suyo). Se usa para que la validación unique no rechace un DNI
     * que coincide con un tutor inactivo -- ver guardar(), que reactiva ese
     * registro en vez de crear uno nuevo.
     */
    private function tutorInactivoConMismoDni(): ?Tutor
    {
        if ($this->tutor) {
            return null;
        }

        return Tutor::where('dni', $this->dni)->where('activo', false)->first();
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
            session()->flash('mensaje', 'Tutor guardado correctamente.');
            $this->redirectRoute('tutores.index', navigate: true);

            return;
        }

        $tutorInactivo = $this->tutorInactivoConMismoDni();

        if ($tutorInactivo) {
            $tutorInactivo->update(array_merge($datos, ['activo' => true]));
            session()->flash('mensaje', 'Tutor reactivado correctamente.');
        } else {
            Tutor::create($datos);
            session()->flash('mensaje', 'Tutor guardado correctamente.');
        }

        $this->redirectRoute('tutores.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.alumnos.tutores.formulario');
    }
}
