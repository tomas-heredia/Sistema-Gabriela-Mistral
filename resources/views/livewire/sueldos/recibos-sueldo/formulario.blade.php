<div class="max-w-xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <h1 class="text-lg font-semibold text-gray-900 mb-6">Nuevo recibo de sueldo</h1>

    <form wire:submit="guardar" class="bg-white shadow-sm rounded-lg p-6 space-y-5">
        <div>
            <x-input-label for="profesor_id" value="Profesor" />
            <select id="profesor_id" wire:model="profesor_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Seleccioná un profesor…</option>
                @foreach ($profesores as $profesor)
                    <option value="{{ $profesor->id }}">{{ $profesor->name }} ({{ $profesor->email }})</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('profesor_id')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="periodo" value="Período" />
            <input id="periodo" type="month" wire:model="periodo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <x-input-error :messages="$errors->get('periodo')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="archivo" value="Archivo (PDF)" />
            <input id="archivo" type="file" wire:model="archivo" accept="application/pdf" class="mt-1 block w-full text-sm text-gray-700">
            <p wire:loading wire:target="archivo" class="text-xs text-gray-500 mt-1">Subiendo…</p>
            <x-input-error :messages="$errors->get('archivo')" class="mt-1" />
        </div>

        <div class="flex items-center gap-3 pt-2">
            <x-primary-button type="submit">Guardar</x-primary-button>
            <a href="{{ route('recibos-sueldo.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                Volver al listado
            </a>
        </div>
    </form>
</div>
