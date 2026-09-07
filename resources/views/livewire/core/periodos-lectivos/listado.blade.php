<div>
    <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-4 mb-6">
            <h1 class="text-lg font-semibold text-gray-900">Períodos lectivos</h1>

            @can('create', \App\Core\Models\PeriodoLectivo::class)
                <a href="{{ route('periodos.crear') }}" wire:navigate>
                    <x-primary-button>Nuevo período</x-primary-button>
                </a>
            @endcan
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            @if ($periodos->isEmpty())
                <p class="text-center text-gray-500 py-12">Todavía no hay ningún período lectivo cargado.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Inicio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fin</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desc. hermanos</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($periodos as $periodo)
                            <tr wire:key="periodo-{{ $periodo->id }}">
                                <td class="px-6 py-3 text-sm text-gray-900">{{ $periodo->nombre }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $periodo->fecha_inicio->format('d/m/Y') }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $periodo->fecha_fin->format('d/m/Y') }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ number_format($periodo->descuento_hermanos_pct, 2) }}%</td>
                                <td class="px-6 py-3 text-sm">
                                    <x-pill :color="$periodo->activo ? 'green' : 'gray'">
                                        {{ $periodo->activo ? 'Activo' : 'Inactivo' }}
                                    </x-pill>
                                </td>
                                <td class="px-6 py-3 text-right text-sm space-x-3 whitespace-nowrap">
                                    @can('update', $periodo)
                                        @unless ($periodo->activo)
                                            <button
                                                type="button"
                                                wire:click="activar({{ $periodo->id }})"
                                                wire:confirm="¿Activar el período {{ $periodo->nombre }}? El período actualmente activo pasa a inactivo."
                                                class="text-green-700 hover:text-green-900"
                                            >
                                                Activar
                                            </button>
                                        @endunless
                                        <a href="{{ route('periodos.editar', $periodo) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800">
                                            Editar
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
