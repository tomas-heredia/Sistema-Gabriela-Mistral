<?php

namespace App\Core\Livewire\Usuarios;

use App\Core\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Formulario extends Component
{
    public const ROLES = [
        'administrador' => 'Administrador',
        'cobrador' => 'Cobrador',
        'profesor' => 'Profesor',
    ];

    public ?User $usuario = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $rol = '';

    public function mount(?User $usuario = null): void
    {
        if ($usuario?->exists) {
            $this->authorize('update', $usuario);

            $this->usuario = $usuario;
            $this->name = $usuario->name;
            $this->email = $usuario->email;
            $this->rol = $usuario->roles->first()?->name ?? '';
        } else {
            $this->authorize('create', User::class);
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->usuario?->id)],
            'password' => [$this->usuario ? 'nullable' : 'required', 'string', 'min:6'],
            'rol' => ['required', Rule::in(array_keys(self::ROLES))],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo',
            'password' => 'contraseña',
            'rol' => 'rol',
        ];
    }

    public function guardar(): void
    {
        $datos = $this->validate();

        if ($this->usuario) {
            $this->usuario->update(['name' => $datos['name'], 'email' => $datos['email']]);
            $this->usuario->syncRoles([$datos['rol']]);
            session()->flash('mensaje', 'Usuario actualizado correctamente.');
        } else {
            $nuevo = User::create([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => $datos['password'],
                'email_verified_at' => now(),
            ]);
            $nuevo->assignRole($datos['rol']);
            session()->flash('mensaje', 'Usuario creado correctamente.');
        }

        $this->redirectRoute('usuarios.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.core.usuarios.formulario');
    }
}
