<div>
    <div class="max-w-6xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-4 mb-6">
            <h1 class="text-lg font-semibold text-gray-900">Libretas{{ $periodoActivo ? " — {$periodoActivo->nombre}" : '' }}</h1>

            <div class="w-full max-w-sm">
                <label for="busqueda" class="sr-only">Buscar por nombre o DNI del alumno</label>
                <input
                    type="text"
                    id="busqueda"
                    wire:model.live.debounce.400ms="busqueda"
                    placeholder="Buscar por nombre o DNI…"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            @if (! $periodoActivo)
                <p class="text-center text-gray-500 py-12">No hay ningún período lectivo activo.</p>
            @elseif ($boletines->isEmpty())
                <p class="text-center text-gray-500 py-12">
                    @if ($busqueda)
                        No encontramos ninguna libreta que coincida con "{{ $busqueda }}".
                    @else
                        Todavía no hay libretas generadas para este período.
                    @endif
                </p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alumno</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trimestres</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($boletines as $boletin)
                            <tr wire:key="boletin-{{ $boletin->id }}">
                                <td class="px-6 py-3 text-sm text-gray-900">{{ $boletin->alumno->nombre }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $boletin->alumno->grado }}</td>
                                <td class="px-6 py-3 text-sm">
                                    <x-pill :color="$boletin->estado->value === 'completo' ? 'green' : 'amber'">
                                        {{ $boletin->estado->value === 'completo' ? 'Completo' : 'En curso' }}
                                    </x-pill>
                                </td>
                                <td class="px-6 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        @foreach ($boletin->trimestres->sortBy('trimestre') as $trimestre)
                                            <a
                                                href="{{ route('boletines.trimestres.cargar', $trimestre) }}"
                                                wire:navigate
                                                class="inline-flex"
                                            >
                                                <x-pill :color="match ($trimestre->estado->value) {
                                                    'enviado' => 'green',
                                                    'cargado' => 'amber',
                                                    default => 'gray',
                                                }">
                                                    {{ $trimestre->trimestre === 4 ? 'Apoyo' : "T{$trimestre->trimestre}" }}
                                                </x-pill>
                                            </a>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @if ($periodoActivo)
            <div class="mt-4">
                {{ $boletines->links() }}
            </div>
        @endif
    </div>
</div>
