<div>
    <div class="max-w-5xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-4 mb-6">
            <h1 class="text-lg font-semibold text-gray-900">
                {{ $esAdministrador ? 'Recibos de sueldo' : 'Mis recibos de sueldo' }}
            </h1>

            @if ($esAdministrador)
                <div class="flex items-center gap-3">
                    <div class="w-full max-w-sm">
                        <label for="busqueda" class="sr-only">Buscar por nombre o correo del profesor</label>
                        <input
                            type="text"
                            id="busqueda"
                            wire:model.live.debounce.400ms="busqueda"
                            placeholder="Buscar por nombre o correo…"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>

                    <a href="{{ route('recibos-sueldo.crear') }}" wire:navigate>
                        <x-primary-button>Nuevo recibo</x-primary-button>
                    </a>
                </div>
            @endif
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            @if ($recibos->isEmpty())
                <p class="text-center text-gray-500 py-12">
                    {{ $esAdministrador ? 'Todavía no hay recibos cargados.' : 'Todavía no tenés recibos cargados.' }}
                </p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            @if ($esAdministrador)
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profesor</th>
                            @endif
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Período</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha de carga</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($recibos as $recibo)
                            <tr wire:key="recibo-{{ $recibo->id }}">
                                @if ($esAdministrador)
                                    <td class="px-6 py-3 text-sm text-gray-900">{{ $recibo->profesor->name }}</td>
                                @endif
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $recibo->periodo }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $recibo->fecha_carga->format('d/m/Y') }}</td>
                                <td class="px-6 py-3 text-right text-sm space-x-3 whitespace-nowrap">
                                    <a href="{{ route('recibos-sueldo.descargar', $recibo) }}" class="text-indigo-600 hover:text-indigo-800">
                                        Descargar
                                    </a>
                                    @if ($esAdministrador)
                                        <button
                                            type="button"
                                            wire:click="eliminar({{ $recibo->id }})"
                                            wire:confirm="¿Eliminar el recibo de {{ $recibo->profesor->name }} del período {{ $recibo->periodo }}?"
                                            class="text-red-600 hover:text-red-800"
                                        >
                                            Eliminar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="mt-4">
            {{ $recibos->links() }}
        </div>
    </div>
</div>
