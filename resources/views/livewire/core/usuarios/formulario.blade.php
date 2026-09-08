<div class="max-w-xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <h1 class="text-lg font-semibold text-gray-900 mb-6">
        {{ $usuario ? 'Editar usuario' : 'Nuevo usuario' }}
    </h1>

    <form wire:submit="guardar" class="bg-white shadow-sm rounded-lg p-6 space-y-5">
        <div>
            <x-input-label for="name" value="Nombre completo" />
            <x-text-input id="name" type="text" class="mt-1 block w-full" wire:model="name" autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="email" value="Correo" />
            <x-text-input id="email" type="email" class="mt-1 block w-full" wire:model="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        @unless ($usuario)
            <div>
                <x-input-label for="password" value="Contraseña" />
                <x-text-input id="password" type="text" class="mt-1 block w-full" wire:model="password" />
                <p class="text-xs text-gray-500 mt-1">Puede ser simple — el usuario la cambia desde su perfil una vez que entra.</p>
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>
        @endunless

        <div>
            <x-input-label for="rol" value="Rol" />
            <select id="rol" wire:model="rol" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Seleccioná un rol…</option>
                @foreach (self::ROLES as $valor => $etiqueta)
                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('rol')" class="mt-1" />
        </div>

        <div class="flex items-center gap-3 pt-2">
            <x-primary-button type="submit">Guardar</x-primary-button>
            <a href="{{ route('usuarios.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                Volver al listado
            </a>
        </div>
    </form>
</div>
