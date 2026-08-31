<div class="max-w-xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <h1 class="text-lg font-semibold text-gray-900 mb-6">
        {{ $tutor ? 'Editar tutor' : 'Nuevo tutor' }}
    </h1>

    <form wire:submit="guardar" class="bg-white shadow-sm rounded-lg p-6 space-y-5">
        <div>
            <x-input-label for="nombre" value="Nombre completo" />
            <x-text-input id="nombre" type="text" class="mt-1 block w-full" wire:model="nombre" autofocus />
            <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="dni" value="DNI" />
            <x-text-input id="dni" type="text" class="mt-1 block w-full" wire:model="dni" />
            <x-input-error :messages="$errors->get('dni')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="domicilio" value="Domicilio" />
            <x-text-input id="domicilio" type="text" class="mt-1 block w-full" wire:model="domicilio" />
            <x-input-error :messages="$errors->get('domicilio')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="telefono" value="Teléfono" />
            <x-text-input id="telefono" type="text" class="mt-1 block w-full" wire:model="telefono" />
            <x-input-error :messages="$errors->get('telefono')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="correo" value="Correo (opcional)" />
            <x-text-input id="correo" type="email" class="mt-1 block w-full" wire:model="correo" />
            <x-input-error :messages="$errors->get('correo')" class="mt-1" />
        </div>

        <div class="flex items-center gap-3 pt-2">
            <x-primary-button type="submit">Guardar</x-primary-button>
            <a href="{{ route('tutores.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                Volver al listado
            </a>
        </div>
    </form>
</div>
