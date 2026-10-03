<div>
    <div class="max-w-6xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-4 mb-6">
            <h1 class="text-lg font-semibold text-gray-900">Libretas{{ $periodoActivo ? " — {$periodoActivo->nombre}" : '' }}</h1>

            <div class="w-full max-w-sm">
                <label for="busqueda" class="sr-only">Buscar por nombre, apellido o DNI del alumno</label>
                <input
                    type="text"
                    id="busqueda"
                    wire:model.live.debounce.400ms="busqueda"
                    placeholder="Buscar por nombre, apellido o DNI…"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
            </div>
        </div>

        @if (session('mensaje'))
            <div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('mensaje') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            @if (! $periodoActivo)
                <p class="text-center text-gray-500 py-12">No hay ningún período lectivo activo.</p>
            @elseif ($busqueda !== '')
                @if ($alumnosBuscados->isEmpty())
                    <p class="text-center text-gray-500 py-12">No encontramos ningún alumno que coincida con "{{ $busqueda }}".</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alumno</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" colspan="2">Libreta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($alumnosBuscados as $alumno)
                                @php $boletin = $alumno->boletines->first(); @endphp
                                <tr wire:key="alumno-{{ $alumno->id }}">
                                    <td class="px-6 py-3 text-sm text-gray-900">{{ $alumno->nombre }}</td>
                                    <td class="px-6 py-3 text-sm text-gray-600">{{ $alumno->grado }}</td>
                                    @if ($boletin)
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
                                    @elseif ($alumno->nivel === \App\Alumnos\Models\Enums\Nivel::Inicial)
                                        <td class="px-6 py-3 text-sm text-gray-400" colspan="2">Nivel inicial no tiene libreta.</td>
                                    @else
                                        <td class="px-6 py-3 text-sm text-gray-500" colspan="2">
                                            <span>Todavía no tiene libreta para este período.</span>
                                            @can('create', \App\Boletines\Models\Boletin::class)
                                                <button
                                                    type="button"
                                                    wire:click="crearLibreta({{ $alumno->id }})"
                                                    wire:confirm="¿Crear la libreta de {{ $alumno->nombre }} para este período?"
                                                    class="ml-2 font-medium text-indigo-600 hover:text-indigo-500"
                                                >
                                                    Crear libreta
                                                </button>
                                            @endcan
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @elseif ($boletines->isEmpty())
                <p class="text-center text-gray-500 py-12">Todavía no hay libretas generadas para este período.</p>
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
                {{ $busqueda !== '' ? $alumnosBuscados->links() : $boletines->links() }}
            </div>
        @endif
    </div>
</div>
