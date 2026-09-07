<div class="max-w-xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-lg font-semibold text-gray-900">
            {{ $periodoLectivo ? 'Editar período' : 'Nuevo período' }}
        </h1>

        @if ($periodoLectivo)
            <x-pill :color="$periodoLectivo->activo ? 'green' : 'gray'">
                {{ $periodoLectivo->activo ? 'Activo' : 'Inactivo' }}
            </x-pill>
        @endif
    </div>

    <form wire:submit="guardar" class="bg-white shadow-sm rounded-lg p-6 space-y-5">
        <div>
            <x-input-label for="nombre" value="Nombre" />
            <x-text-input id="nombre" type="text" class="mt-1 block w-full" wire:model="nombre" autofocus />
            <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="fecha_inicio" value="Fecha de inicio" />
                <x-text-input id="fecha_inicio" type="date" class="mt-1 block w-full" wire:model="fecha_inicio" />
                <x-input-error :messages="$errors->get('fecha_inicio')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="fecha_fin" value="Fecha de fin" />
                <x-text-input id="fecha_fin" type="date" class="mt-1 block w-full" wire:model="fecha_fin" />
                <x-input-error :messages="$errors->get('fecha_fin')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="descuento_hermanos_pct" value="Descuento por hermanos (%)" />
            <x-text-input id="descuento_hermanos_pct" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" wire:model="descuento_hermanos_pct" />
            <x-input-error :messages="$errors->get('descuento_hermanos_pct')" class="mt-1" />
        </div>

        <div>
            <x-input-label value="Aranceles" />
            <p class="text-sm text-gray-500 mb-2">Montos en pesos. Hacen falta los 4 para poder generar cuotas en este período.</p>

            <table class="min-w-full">
                <thead>
                    <tr>
                        <th></th>
                        @foreach ($tipos as $tipo)
                            <th class="px-2 py-1 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ $tipo === \App\Cobranzas\Models\Enums\TipoCuota::Matricula ? 'Matrícula' : 'Mensualidad' }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($niveles as $nivel)
                        <tr>
                            <td class="px-2 py-1 text-sm text-gray-700">{{ ucfirst($nivel->value) }}</td>
                            @foreach ($tipos as $tipo)
                                @php $clave = "{$nivel->value}_{$tipo->value}"; @endphp
                                <td class="px-2 py-1">
                                    <x-text-input type="number" step="0.01" min="0" class="block w-full" wire:model="montos.{{ $clave }}" />
                                    <x-input-error :messages="$errors->get(\"montos.$clave\")" class="mt-1" />
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <x-primary-button type="submit">Guardar</x-primary-button>
            <a href="{{ route('periodos.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                Volver al listado
            </a>
        </div>
    </form>
</div>
