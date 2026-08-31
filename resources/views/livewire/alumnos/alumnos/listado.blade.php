<div>
    <div class="max-w-6xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div class="flex flex-wrap items-center gap-3">
                <div class="w-full max-w-sm">
                    <label for="busqueda" class="sr-only">Buscar por nombre o DNI</label>
                    <input
                        type="text"
                        id="busqueda"
                        wire:model.live.debounce.400ms="busqueda"
                        placeholder="Buscar por nombre o DNI…"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>

                <div>
                    <label for="nivel" class="sr-only">Filtrar por nivel</label>
                    <select id="nivel" wire:model.live="nivel" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Todos los niveles</option>
                        @foreach ($niveles as $opcion)
                            <option value="{{ $opcion->value }}">{{ ucfirst($opcion->value) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <a href="{{ route('alumnos.crear') }}" wire:navigate>
                <x-primary-button>Nuevo alumno</x-primary-button>
            </a>
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            @if ($alumnos->isEmpty())
                <p class="text-center text-gray-500 py-12">
                    @if ($busqueda || $nivel)
                        No encontramos ningún alumno que coincida con la búsqueda.
                    @else
                        Todavía no hay alumnos cargados.
                    @endif
                </p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DNI</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nivel / Grado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turno</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($alumnos as $alumno)
                            <tr wire:key="alumno-{{ $alumno->id }}">
                                <td class="px-6 py-3 text-sm text-gray-900">{{ $alumno->nombre }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $alumno->dni ?? '—' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ ucfirst($alumno->nivel->value) }} · {{ $alumno->grado }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ ucfirst($alumno->turno->value) }}</td>
                                <td class="px-6 py-3 text-sm">
                                    @if ($alumno->activo)
                                        <x-pill color="green">Activo</x-pill>
                                    @else
                                        <x-pill color="gray">Inactivo</x-pill>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right text-sm space-x-3 whitespace-nowrap">
                                    <a href="{{ route('alumnos.editar', $alumno) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800">Editar</a>
                                    <button
                                        type="button"
                                        wire:click="eliminar({{ $alumno->id }})"
                                        wire:confirm="¿Seguro que querés eliminar a {{ $alumno->nombre }}?"
                                        class="text-red-600 hover:text-red-800"
                                    >
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="mt-4">
            {{ $alumnos->links() }}
        </div>
    </div>
</div>
