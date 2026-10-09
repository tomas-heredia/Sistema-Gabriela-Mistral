<div class="max-w-5xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
    <h1 class="text-lg font-semibold text-gray-900">Registrar pago</h1>

    <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
        <div class="flex items-end gap-3">
            <div class="flex-1 max-w-sm">
                <x-input-label for="busquedaTutor" value="Buscar por DNI o nombre del tutor, o por nombre/apellido o DNI de un alumno" />
                <x-text-input id="busquedaTutor" type="text" class="mt-1 block w-full" wire:model="busquedaTutor" />
            </div>
            <x-secondary-button type="button" wire:click="buscarTutor">Buscar</x-secondary-button>
        </div>

        @if ($buscoTutor && ! $tutorEncontrado && empty($resultadosBusqueda))
            <p class="text-sm text-gray-500">No encontramos ningún tutor con esos datos.</p>
        @endif

        @if (! empty($resultadosBusqueda))
            <div class="border border-gray-200 rounded-md divide-y divide-gray-100">
                @foreach ($resultadosBusqueda as $resultado)
                    <button
                        type="button"
                        wire:click="seleccionarTutor({{ $resultado['id'] }})"
                        class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 flex items-center justify-between"
                    >
                        <span class="text-gray-900">{{ $resultado['nombre'] }}</span>
                        <span class="text-gray-500">DNI {{ $resultado['dni'] }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        @if ($tutorEncontrado)
            <p class="text-sm text-gray-900">Tutor: <strong>{{ $tutorEncontrado->nombre }}</strong></p>
        @endif
    </div>

    @if (! empty($comprobantesGenerados))
        <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
            <p class="text-sm font-medium text-green-700">
                Pago registrado correctamente. Se generó un comprobante por cada mes pagado y se envió por mail al tutor.
            </p>

            <div class="border border-gray-200 rounded-md divide-y divide-gray-100">
                @foreach ($comprobantesGenerados as $comprobante)
                    <div class="px-4 py-3 flex items-center justify-between text-sm">
                        <span class="text-gray-900">
                            Recibo N° {{ $comprobante['numeroRecibo'] }} — {{ $comprobante['alumno'] }} — Período {{ $comprobante['periodo'] }}
                        </span>
                        <a href="{{ $comprobante['url'] }}" target="_blank" class="text-indigo-600 hover:text-indigo-500 font-medium">
                            Descargar
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center gap-3 pt-2">
                <x-primary-button type="button" wire:click="nuevoPago">Registrar otro pago</x-primary-button>
                <a href="{{ route('pagos.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                    Ir al listado de pagos
                </a>
            </div>
        </div>
    @elseif ($tutorEncontrado)
        <form wire:submit="guardar" class="space-y-6">
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                @if ($cuotas->isEmpty())
                    <p class="text-center text-gray-500 py-8 text-sm">
                        Este tutor no tiene ninguna cuota pendiente en el período activo.
                    </p>
                @else
                    <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2"></th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alumno</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DNI</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nivel / Grado</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turno</th>
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
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $cuota->alumno->dni ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ ucfirst($cuota->alumno->nivel->value) }} · {{ $cuota->alumno->grado }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ ucfirst($cuota->alumno->turno->value) }}</td>
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
                    </div>
                @endif
            </div>

            <x-input-error :messages="$errors->get('cuotasSeleccionadas')" />

            <div class="bg-white shadow-sm rounded-lg p-6 space-y-5">
                <p class="text-base text-gray-900">
                    Total a registrar: <strong>${{ number_format($this->montoTotal / 100, 2, ',', '.') }}</strong>
                </p>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="medio_pago" value="Medio de pago" />
                        <select id="medio_pago" wire:model="medio_pago" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Seleccioná…</option>
                            @foreach ($medios as $opcion)
                                <option value="{{ $opcion->value }}">{{ $opcion->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('medio_pago')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="fecha" value="Fecha" />
                        <x-text-input id="fecha" type="date" class="mt-1 block w-full" wire:model="fecha" />
                        <x-input-error :messages="$errors->get('fecha')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="interesPorcentaje" value="Interés (%, opcional)" />
                        <input
                            type="number" step="0.01" min="0" max="100"
                            id="interesPorcentaje"
                            wire:model.live="interesPorcentaje"
                            placeholder="0"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        <p class="mt-1 text-xs text-gray-500">Se aplica por separado sobre la deuda de cada cuota tildada, no sobre el total.</p>
                        <x-input-error :messages="$errors->get('interesPorcentaje')" class="mt-1" />
                    </div>
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

    @if ($tutorEncontrado)
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <h2 class="px-6 py-3 text-sm font-semibold text-gray-900 border-b border-gray-200">
                Historial de pagos de {{ $tutorEncontrado->nombre }}
            </h2>

            @if ($this->historialPagos->isEmpty())
                <p class="text-center text-gray-500 py-8 text-sm">Este tutor todavía no tiene pagos registrados.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monto</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medio</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aplicado a</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($this->historialPagos as $pago)
                                <tr wire:key="historial-pago-{{ $pago->id }}">
                                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{{ $pago->fecha->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">${{ number_format($pago->monto / 100, 2, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{{ $pago->medio_pago->label() }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <div class="flex flex-col gap-1">
                                            @foreach ($pago->pagoCuotas as $pagoCuota)
                                                <a
                                                    href="{{ route('pagos.comprobantes.descargar', $pagoCuota) }}"
                                                    target="_blank"
                                                    class="text-indigo-600 hover:text-indigo-500"
                                                >
                                                    {{ $pagoCuota->cuota->alumno->nombre }} — {{ str_pad((string) $pagoCuota->cuota->mes, 2, '0', STR_PAD_LEFT) }}/{{ $pagoCuota->cuota->periodoLectivo->nombre }} (recibo {{ $pagoCuota->numero_recibo }})
                                                </a>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm whitespace-nowrap">
                                        @if ($pago->estaAnulado())
                                            <x-pill color="red">Anulado</x-pill>
                                        @else
                                            <x-pill color="green">Vigente</x-pill>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
