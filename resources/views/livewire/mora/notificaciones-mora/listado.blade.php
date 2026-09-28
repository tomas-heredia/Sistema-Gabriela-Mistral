<div>
    <div class="max-w-6xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <h1 class="text-lg font-semibold text-gray-900">Tutores en mora</h1>

            <div class="flex flex-wrap items-center gap-3">
                <div class="w-full max-w-sm">
                    <label for="busqueda" class="sr-only">Buscar por nombre, apellido o DNI del tutor</label>
                    <input
                        type="text"
                        id="busqueda"
                        wire:model.live.debounce.400ms="busqueda"
                        placeholder="Buscar por nombre, apellido o DNI del tutor…"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>

                <div class="flex items-center gap-2">
                    <div>
                        <label for="desde" class="sr-only">Vencimiento desde</label>
                        <input
                            type="date"
                            id="desde"
                            wire:model.live="desde"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>
                    <span class="text-gray-500 text-sm">a</span>
                    <div>
                        <label for="hasta" class="sr-only">Vencimiento hasta</label>
                        <input
                            type="date"
                            id="hasta"
                            wire:model.live="hasta"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            @if ($morosos->isEmpty())
                <p class="text-center text-gray-500 py-12">
                    @if ($busqueda || $desde || $hasta)
                        No encontramos ningún tutor en mora que coincida con los filtros.
                    @else
                        No hay ningún tutor en mora en este momento.
                    @endif
                </p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tutor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monto adeudado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meses</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($morosos as $fila)
                            <tr wire:key="moroso-{{ $fila['tutor']->id }}">
                                <td class="px-6 py-3 text-sm text-gray-900">
                                    {{ $fila['tutor']->nombre }}
                                    <span class="text-gray-500">({{ $fila['tutor']->dni }})</span>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600">${{ number_format($fila['monto_adeudado'] / 100, 2, ',', '.') }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $fila['meses_adeudados'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="mt-4">
            {{ $morosos->links() }}
        </div>
    </div>
</div>
