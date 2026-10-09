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

    public bool $puedeCargarBoletines = false;

    public bool $activo = true;

    public function mount(?User $usuario = null): void
    {
        if ($usuario?->exists) {
            $this->authorize('update', $usuario);

            $this->usuario = $usuario;
            $this->name = $usuario->name;
            $this->email = $usuario->email;
            $this->rol = $usuario->roles->first()?->name ?? '';
            $this->puedeCargarBoletines = $usuario->hasPermissionTo('cargar_boletines');
            $this->activo = $usuario->activo;
        } else {
            $this->authorize('create', User::class);
        }
    }

    /**
     * Administrador puede crear/asignar cualquier rol; cobrador solo puede
     * crear profesores (ver UserPolicy::create) -- esto es lo que de verdad
     * lo hace cumplir, la lista de ROLES completa es solo para el <select>
     * de un administrador.
     */
    public function rolesDisponibles(): array
    {
        return auth()->user()->hasRole('administrador') ? self::ROLES : ['profesor' => 'Profesor'];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->usuario?->id)],
            'password' => [$this->usuario ? 'nullable' : 'required', 'string', 'min:6'],
            'rol' => ['required', Rule::in(array_keys($this->rolesDisponibles()))],
            'activo' => ['boolean'],
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
            $this->usuario->update(['name' => $datos['name'], 'email' => $datos['email'], 'activo' => $datos['activo']]);
            $this->usuario->syncRoles([$datos['rol']]);
            $this->sincronizarPermisoDeBoletines($this->usuario, $datos['rol']);
            session()->flash('mensaje', 'Usuario actualizado correctamente.');
        } else {
            $nuevo = User::create([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => $datos['password'],
                'email_verified_at' => now(),
            ]);
            $nuevo->assignRole($datos['rol']);
            $this->sincronizarPermisoDeBoletines($nuevo, $datos['rol']);
            session()->flash('mensaje', 'Usuario creado correctamente.');
        }

        // Un cobrador puede crear profesores pero no ve el listado de
        // usuarios (ver UserPolicy::viewAny) -- mandarlo ahí lo dejaría
        // frente a un 403 justo después de guardar con éxito.
        $destino = auth()->user()->can('viewAny', User::class) ? 'usuarios.index' : 'dashboard';
        $this->redirectRoute($destino, navigate: true);
    }

    /**
     * 'cargar_boletines' solo tiene sentido sobre profesor -- si el checkbox
     * quedó tildado pero el rol elegido no es profesor (o cambió a otro
     * rol), no se le deja el permiso colgado.
     */
    private function sincronizarPermisoDeBoletines(User $usuario, string $rol): void
    {
        if ($rol === 'profesor' && $this->puedeCargarBoletines) {
            $usuario->givePermissionTo('cargar_boletines');
        } else {
            $usuario->revokePermissionTo('cargar_boletines');
        }
    }

    public function render()
    {
        return view('livewire.core.usuarios.formulario');
    }
}
