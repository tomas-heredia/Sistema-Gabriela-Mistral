<div>
    <div class="max-w-6xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <h1 class="text-lg font-semibold text-gray-900">Notificaciones de mora</h1>

            <div class="flex flex-wrap items-center gap-3">
                <div class="w-full max-w-sm">
                    <label for="busqueda" class="sr-only">Buscar por nombre o DNI del tutor</label>
                    <input
                        type="text"
                        id="busqueda"
                        wire:model.live.debounce.400ms="busqueda"
                        placeholder="Buscar por nombre o DNI del tutor…"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>

                <div>
                    <label for="estado" class="sr-only">Filtrar por estado</label>
                    <select id="estado" wire:model.live="estado" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Todos los estados</option>
                        @foreach ($estados as $opcion)
                            <option value="{{ $opcion->value }}">{{ ucfirst($opcion->value) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            @if ($notificaciones->isEmpty())
                <p class="text-center text-gray-500 py-12">
                    @if ($busqueda || $estado)
                        No encontramos ninguna notificación que coincida con los filtros.
                    @else
                        Todavía no hay notificaciones de mora generadas.
                    @endif
                </p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tutor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monto adeudado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meses</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enviado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Intentos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($notificaciones as $notificacion)
                            <tr wire:key="notificacion-{{ $notificacion->id }}">
                                <td class="px-6 py-3 text-sm text-gray-900">
                                    {{ $notificacion->tutor->nombre }}
                                    <span class="text-gray-500">({{ $notificacion->tutor->dni }})</span>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600">${{ number_format($notificacion->monto_adeudado / 100, 2, ',', '.') }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $notificacion->meses_adeudados }}</td>
                                <td class="px-6 py-3 text-sm">
                                    <x-pill :color="match ($notificacion->estado->value) {
                                        'enviado' => 'green',
                                        'fallido' => 'red',
                                        default => 'gray',
                                    }">
                                        {{ ucfirst($notificacion->estado->value) }}
                                    </x-pill>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $notificacion->fecha->format('d/m/Y') }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $notificacion->fecha_envio?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $notificacion->intentos }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="mt-4">
            {{ $notificaciones->links() }}
        </div>
    </div>
</div>
