<div>
    <div class="max-w-6xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between gap-4 mb-6">
            <div class="w-full max-w-sm">
                <label for="busqueda" class="sr-only">Buscar por número de recibo o tutor</label>
                <input
                    type="text"
                    id="busqueda"
                    wire:model.live.debounce.400ms="busqueda"
                    placeholder="Buscar por N° de recibo o tutor…"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
            </div>

            <a href="{{ route('pagos.registrar') }}" wire:navigate>
                <x-primary-button>Registrar pago</x-primary-button>
            </a>
        </div>

        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            @if ($pagos->isEmpty())
                <p class="text-center text-gray-500 py-12">
                    @if ($busqueda)
                        No encontramos ningún pago que coincida con "{{ $busqueda }}".
                    @else
                        Todavía no hay pagos registrados.
                    @endif
                </p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tutor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N° recibo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($pagos as $pago)
                            <tr wire:key="pago-{{ $pago->id }}">
                                <td class="px-6 py-3 text-sm text-gray-900">{{ $pago->tutor->nombre }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">${{ number_format($pago->monto / 100, 2, ',', '.') }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ ucfirst($pago->medio_pago->value) }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $pago->fecha->format('d/m/Y') }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $pago->numero_recibo }}</td>
                                <td class="px-6 py-3 text-sm">
                                    @if ($pago->estaAnulado())
                                        <x-pill color="red">Anulado</x-pill>
                                    @else
                                        <x-pill color="green">Vigente</x-pill>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right text-sm">
                                    @can('anular', $pago)
                                        @if (! $pago->estaAnulado())
                                            <button type="button" wire:click="prepararAnulacion({{ $pago->id }})" class="text-red-600 hover:text-red-800">
                                                Anular
                                            </button>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="mt-4">
            {{ $pagos->links() }}
        </div>
    </div>

    <x-modal name="anular-pago" focusable>
        <div class="p-6">
            <h2 class="text-lg font-semibold text-gray-900">Anular pago</h2>
            <p class="mt-1 text-sm text-gray-600">
                Este pago queda marcado como anulado (no se borra) y las cuotas que tenía aplicadas vuelven a su estado anterior.
            </p>

            <div class="mt-4">
                <x-input-label for="motivoAnulacion" value="Motivo" />
                <textarea id="motivoAnulacion" wire:model="motivoAnulacion" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <x-input-error :messages="$errors->get('motivoAnulacion')" class="mt-1" />
            </div>

            <div class="mt-5 flex gap-3">
                <x-danger-button type="button" wire:click="anular">Confirmar anulación</x-danger-button>
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'anular-pago')">Cancelar</x-secondary-button>
            </div>
        </div>
    </x-modal>
</div>
