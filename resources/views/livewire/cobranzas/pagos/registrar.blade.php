<div class="max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
    <h1 class="text-lg font-semibold text-gray-900">Registrar pago</h1>

    <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
        <div class="flex items-end gap-3">
            <div class="flex-1 max-w-xs">
                <x-input-label for="dniTutorBuscado" value="DNI del tutor que paga" />
                <x-text-input id="dniTutorBuscado" type="text" class="mt-1 block w-full" wire:model="dniTutorBuscado" />
            </div>
            <x-secondary-button type="button" wire:click="buscarTutor">Buscar</x-secondary-button>
        </div>

        @if ($buscoTutor && ! $tutorEncontrado)
            <p class="text-sm text-gray-500">No encontramos ningún tutor con ese DNI.</p>
        @endif

        @if ($tutorEncontrado)
            <p class="text-sm text-gray-900">Tutor: <strong>{{ $tutorEncontrado->nombre }}</strong></p>
        @endif
    </div>

    @if ($tutorEncontrado)
        <form wire:submit="guardar" class="space-y-6">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                @if ($cuotas->isEmpty())
                    <p class="text-center text-gray-500 py-8 text-sm">
                        Este tutor no tiene ninguna cuota pendiente en el período activo.
                    </p>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2"></th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alumno</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Concepto</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo pendiente</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monto a aplicar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($cuotas as $cuota)
                                @php $saldo = $cuota->monto - $cuota->montoPagado(); @endphp
                                <tr wire:key="cuota-pago-{{ $cuota->id }}">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" wire:model.live="cuotasSeleccionadas.{{ $cuota->id }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $cuota->alumno->nombre }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ $cuota->tipo->value === 'matricula' ? 'Matrícula' : "Mensualidad — mes {$cuota->mes}" }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">${{ number_format($saldo / 100, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3">
                                        <input
                                            type="number" step="0.01" min="0.01" max="{{ $saldo / 100 }}"
                                            wire:model.live="montos.{{ $cuota->id }}"
                                            class="w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <x-input-error :messages="$errors->get('cuotasSeleccionadas')" />

            <div class="bg-white shadow-sm rounded-lg p-6 space-y-5">
                <p class="text-base text-gray-900">
                    Total a registrar: <strong>${{ number_format($this->montoTotal / 100, 2, ',', '.') }}</strong>
                </p>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="medio_pago" value="Medio de pago" />
                        <select id="medio_pago" wire:model="medio_pago" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Seleccioná…</option>
                            @foreach ($medios as $opcion)
                                <option value="{{ $opcion->value }}">{{ ucfirst($opcion->value) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('medio_pago')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="fecha" value="Fecha" />
                        <x-text-input id="fecha" type="date" class="mt-1 block w-full" wire:model="fecha" />
                        <x-input-error :messages="$errors->get('fecha')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="numero_recibo" value="Número de recibo" />
                    <x-text-input id="numero_recibo" type="text" class="mt-1 block w-full" wire:model="numero_recibo" />
                    <x-input-error :messages="$errors->get('numero_recibo')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="observaciones" value="Observaciones (opcional)" />
                    <textarea id="observaciones" wire:model="observaciones" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button type="submit">Registrar pago</x-primary-button>
                    <a href="{{ route('pagos.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                        Cancelar
                    </a>
                </div>
            </div>
        </form>
    @endif
</div>
