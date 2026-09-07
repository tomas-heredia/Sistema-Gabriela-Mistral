<div class="max-w-5xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">
                {{ $boletinTrimestre->boletin->alumno->nombre }} —
                {{ $boletinTrimestre->trimestre === 4 ? 'Etapa de Apoyo' : "Trimestre {$boletinTrimestre->trimestre}" }}
            </h1>
            <p class="text-sm text-gray-500">{{ $boletinTrimestre->boletin->plantilla->nombre }}</p>
        </div>

        <x-pill :color="match ($boletinTrimestre->estado->value) {
            'enviado' => 'green',
            'cargado' => 'amber',
            default => 'gray',
        }">
            {{ ucfirst($boletinTrimestre->estado->value) }}
        </x-pill>
    </div>

    @if ($soloLectura)
        <p class="text-sm text-gray-500">Este trimestre ya fue enviado y no se puede editar.</p>
    @endif

    <form wire:submit="guardarBorrador" class="space-y-6">
        @foreach ($secciones as $entry)
            @php $seccion = $entry['seccion']; @endphp

            <div class="bg-white shadow-sm rounded-lg p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">{{ $seccion['titulo'] ?? $seccion['id'] }}</h2>

                @if (str_starts_with($seccion['tipo'], 'tabla_'))
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                    @foreach ($entry['columnas'] as $columna)
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $columna['nombre'] }}</th>
                                    @endforeach
                                    @if ($seccion['filas_libres'] ?? false)
                                        <th class="px-3 py-2"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach (($datos[$seccion['id']] ?? []) as $indice => $fila)
                                    <tr wire:key="{{ $seccion['id'] }}-fila-{{ $indice }}">
                                        <td class="px-3 py-2 text-sm text-gray-900">
                                            @if ($seccion['filas_libres'] ?? false)
                                                <input
                                                    type="text"
                                                    wire:model="datos.{{ $seccion['id'] }}.{{ $indice }}.nombre"
                                                    @disabled($soloLectura)
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                >
                                            @else
                                                {{ $fila['nombre'] }}
                                            @endif
                                        </td>
                                        @foreach ($entry['columnas'] as $columna)
                                            <td class="px-3 py-2 text-sm">
                                                @php $tipo = $this->tipoInput($seccion, $columna); @endphp
                                                @if ($tipo === 'select')
                                                    <select
                                                        wire:model="datos.{{ $seccion['id'] }}.{{ $indice }}.{{ $columna['id'] }}"
                                                        @disabled($soloLectura)
                                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    >
                                                        <option value="">—</option>
                                                        @foreach ($seccion['escala']['opciones'] as $opcion)
                                                            <option value="{{ $opcion }}">{{ $this->etiquetaOpcion($seccion, $opcion) }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    @php [$min, $max] = $tipo === 'number' ? $this->limitesNumericos($seccion, $columna) : [null, null]; @endphp
                                                    <input
                                                        type="{{ $tipo }}"
                                                        wire:model="datos.{{ $seccion['id'] }}.{{ $indice }}.{{ $columna['id'] }}"
                                                        @disabled($soloLectura)
                                                        @if (! is_null($min)) min="{{ $min }}" @endif
                                                        @if (! is_null($max)) max="{{ $max }}" @endif
                                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    >
                                                @endif
                                            </td>
                                        @endforeach
                                        @if ($seccion['filas_libres'] ?? false)
                                            <td class="px-3 py-2 text-right">
                                                @unless ($soloLectura)
                                                    <button type="button" wire:click="quitarFila('{{ $seccion['id'] }}', {{ $indice }})" class="text-red-600 hover:text-red-800 text-sm">
                                                        Quitar
                                                    </button>
                                                @endunless
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if (($seccion['filas_libres'] ?? false) && ! $soloLectura)
                        <x-secondary-button type="button" wire:click="agregarFila('{{ $seccion['id'] }}')" class="mt-3">
                            Agregar fila
                        </x-secondary-button>
                    @endif
                @elseif ($seccion['tipo'] === 'campo_seleccion')
                    <select
                        wire:model="datos.{{ $seccion['id'] }}"
                        @disabled($soloLectura)
                        class="block w-full max-w-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">—</option>
                        @foreach ($seccion['opciones'] as $opcion)
                            <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>
                @elseif ($seccion['tipo'] === 'campo_texto')
                    <textarea
                        wire:model="datos.{{ $seccion['id'] }}"
                        @disabled($soloLectura)
                        rows="3"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    ></textarea>
                @else
                    @php [$min, $max] = $this->limitesNumericos($seccion); @endphp
                    <input
                        type="number"
                        wire:model="datos.{{ $seccion['id'] }}"
                        @disabled($soloLectura)
                        @if (! is_null($min)) min="{{ $min }}" @endif
                        @if (! is_null($max)) max="{{ $max }}" @endif
                        class="block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                @endif
            </div>
        @endforeach

        <div class="flex items-center gap-3">
            <a href="{{ route('boletines.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                Volver al listado
            </a>

            @unless ($soloLectura)
                <x-primary-button type="submit">Guardar borrador</x-primary-button>

                @if ($boletinTrimestre->estado->value === 'cargado')
                    <x-secondary-button type="button" x-on:click="$dispatch('open-modal', 'confirmar-envio')">
                        Confirmar y enviar
                    </x-secondary-button>
                @endif
            @endunless
        </div>
    </form>

    @unless ($soloLectura)
        <x-modal name="confirmar-envio" focusable>
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">Confirmar y enviar</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Esto genera el PDF y lo envía por mail a los tutores del alumno. Una vez enviado, este trimestre no se puede volver a editar.
                </p>

                <div class="mt-5 flex gap-3">
                    <x-primary-button type="button" wire:click="confirmarYEnviar">Confirmar y enviar</x-primary-button>
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'confirmar-envio')">Cancelar</x-secondary-button>
                </div>
            </div>
        </x-modal>
    @endunless
</div>
