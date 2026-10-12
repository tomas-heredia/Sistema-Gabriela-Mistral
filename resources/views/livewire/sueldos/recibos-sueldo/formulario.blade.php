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

        <div
            x-data="{
                archivos: [],
                agregar(event) {
                    this.archivos = this.archivos.concat(Array.from(event.target.files));
                    event.target.value = '';
                    this.sincronizar();
                },
                quitar(indice) {
                    this.archivos.splice(indice, 1);
                    this.sincronizar();
                },
                sincronizar() {
                    const datos = new DataTransfer();
                    this.archivos.forEach((archivo) => datos.items.add(archivo));
                    this.$refs.archivosLivewire.files = datos.files;
                    this.$refs.archivosLivewire.dispatchEvent(new Event('change'));
                },
            }"
        >
            <x-input-label for="archivos_selector" value="Archivos (PDF)" />
            <p class="text-xs text-gray-500 mt-1">Podés elegirlos todos juntos o agregarlos de a uno.</p>
            <input
                id="archivos_selector"
                type="file"
                multiple
                accept="application/pdf"
                x-on:change="agregar($event)"
                class="mt-1 block w-full text-sm text-gray-700"
            >
            <input type="file" multiple x-ref="archivosLivewire" wire:model="archivos" class="hidden">

            <p wire:loading wire:target="archivos" class="text-xs text-gray-500 mt-1">Subiendo…</p>

            <ul class="mt-2 text-sm text-gray-600 divide-y divide-gray-100" x-show="archivos.length > 0">
                <template x-for="(archivo, indice) in archivos" :key="indice">
                    <li class="flex items-center justify-between py-1">
                        <span x-text="archivo.name"></span>
                        <button type="button" x-on:click="quitar(indice)" class="text-red-600 hover:text-red-800 text-xs">Quitar</button>
                    </li>
                </template>
            </ul>

            <x-input-error :messages="$errors->get('archivos')" class="mt-1" />
            <x-input-error :messages="\Illuminate\Support\Arr::flatten($errors->get('archivos.*'))" class="mt-1" />
        </div>

        <div class="flex items-center gap-3 pt-2">
            <x-primary-button type="submit">Guardar</x-primary-button>
            <a href="{{ route('recibos-sueldo.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                Volver al listado
            </a>
        </div>
    </form>
</div>
