<div class="max-w-xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-8">
    <div>
        <h1 class="text-lg font-semibold text-gray-900 mb-6">
            {{ $alumno ? 'Editar alumno' : 'Nuevo alumno' }}
        </h1>

        <form wire:submit="guardar" class="bg-white shadow-sm rounded-lg p-6 space-y-5">
            <div>
                <x-input-label for="nombre" value="Nombre completo" />
                <x-text-input id="nombre" type="text" class="mt-1 block w-full" wire:model="nombre" autofocus />
                <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="dni" value="DNI (opcional)" />
                <x-text-input id="dni" type="text" class="mt-1 block w-full" wire:model="dni" />
                <x-input-error :messages="$errors->get('dni')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="fecha_nacimiento" value="Fecha de nacimiento" />
                <x-text-input id="fecha_nacimiento" type="date" class="mt-1 block w-full" wire:model="fecha_nacimiento" />
                <x-input-error :messages="$errors->get('fecha_nacimiento')" class="mt-1" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="nivel" value="Nivel" />
                    <select id="nivel" wire:model.live="nivel" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($niveles as $opcion)
                            <option value="{{ $opcion->value }}">{{ ucfirst($opcion->value) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('nivel')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="turno" value="Turno" />
                    <select id="turno" wire:model="turno" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($turnos as $opcion)
                            <option value="{{ $opcion->value }}">{{ ucfirst($opcion->value) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('turno')" class="mt-1" />
                </div>
            </div>

            @if ($nivel === \App\Alumnos\Models\Enums\Nivel::Primario->value)
                <div>
                    <x-input-label for="grado" value="Grado" />
                    <select id="grado" wire:model="grado" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Seleccioná un grado…</option>
                        @foreach (self::GRADOS_PRIMARIO as $opcion)
                            <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('grado')" class="mt-1" />
                </div>
            @endif

            @if ($nivel === \App\Alumnos\Models\Enums\Nivel::Secundario->value)
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="anio_secundaria" value="Año" />
                        <select id="anio_secundaria" wire:model.live="anio_secundaria" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Seleccioná un año…</option>
                            @for ($anio = 1; $anio <= 6; $anio++)
                                <option value="{{ $anio }}">{{ $anio }}º año</option>
                            @endfor
                        </select>
                        <x-input-error :messages="$errors->get('anio_secundaria')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="division" value="División (opcional)" />
                        <x-text-input id="division" type="text" class="mt-1 block w-full" wire:model="division" />
                        <x-input-error :messages="$errors->get('division')" class="mt-1" />
                    </div>
                </div>
            @endif

            <div>
                <x-input-label for="libro_folio" value="Libro y folio (opcional)" />
                <x-text-input id="libro_folio" type="text" class="mt-1 block w-full" wire:model="libro_folio" />
                <x-input-error :messages="$errors->get('libro_folio')" class="mt-1" />
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="activo" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Alumno activo</span>
            </label>

            <div class="flex items-center gap-3 pt-2">
                <x-primary-button type="submit">Guardar</x-primary-button>
                <a href="{{ route('alumnos.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                    Volver al listado
                </a>
            </div>
        </form>
    </div>

    @if ($alumno)
        <div>
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Tutores vinculados</h2>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-4">
                @if ($tutoresVinculados->isEmpty())
                    <p class="text-center text-gray-500 py-8 text-sm">
                        Este alumno todavía no tiene ningún tutor vinculado.
                    </p>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vínculo</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Responsable de pago</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($tutoresVinculados as $tutorVinculado)
                                <tr wire:key="tutor-vinculado-{{ $tutorVinculado->id }}">
                                    <td class="px-4 py-2 text-sm text-gray-900">{{ $tutorVinculado->nombre }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ self::VINCULOS[$tutorVinculado->pivot->vinculo] ?? $tutorVinculado->pivot->vinculo }}</td>
                                    <td class="px-4 py-2 text-sm">
                                        @if ($tutorVinculado->pivot->responsable_pago)
                                            <x-pill color="green">Sí</x-pill>
                                        @else
                                            <x-pill color="gray">No</x-pill>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right text-sm">
                                        <button
                                            type="button"
                                            wire:click="desvincularTutor({{ $tutorVinculado->id }})"
                                            wire:confirm="¿Quitar a {{ $tutorVinculado->nombre }} como tutor de este alumno?"
                                            class="text-red-600 hover:text-red-800"
                                        >
                                            Quitar
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <p class="text-sm font-medium text-gray-700">Vincular un tutor existente</p>

                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <x-input-label for="dniTutorBuscado" value="DNI del tutor" />
                        <x-text-input id="dniTutorBuscado" type="text" class="mt-1 block w-full" wire:model="dniTutorBuscado" />
                    </div>
                    <x-secondary-button type="button" wire:click="buscarTutor">Buscar</x-secondary-button>
                </div>
                <x-input-error :messages="$errors->get('dniTutorBuscado')" />

                @if ($buscoTutor)
                    @if ($tutorEncontrado)
                        <div class="border border-gray-200 rounded-md p-4 space-y-4">
                            <p class="text-sm text-gray-900">
                                Encontrado: <strong>{{ $tutorEncontrado->nombre }}</strong>
                            </p>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="vinculoNuevo" value="Vínculo" />
                                    <select id="vinculoNuevo" wire:model="vinculoNuevo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach (self::VINCULOS as $valor => $etiqueta)
                                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <label class="flex items-center gap-2 mt-6">
                                    <input type="checkbox" wire:model="responsablePagoNuevo" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">Responsable de pago</span>
                                </label>
                            </div>

                            <x-primary-button type="button" wire:click="vincularTutor">Vincular</x-primary-button>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">
                            No encontramos ningún tutor con ese DNI.
                            <a href="{{ route('tutores.crear') }}" wire:navigate class="text-indigo-600 hover:text-indigo-800">Crear uno nuevo</a>.
                        </p>
                    @endif
                @endif
            </div>
        </div>

        <div>
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Cobranzas</h2>

            <div class="bg-white shadow-sm rounded-lg p-6">
                @if (! $periodoActivo)
                    <p class="text-sm text-gray-500">No hay ningún período lectivo activo — no se pueden generar cuotas.</p>
                @elseif ($cuotas->isEmpty())
                    <p class="text-sm text-gray-600 mb-4">Este alumno todavía no tiene cuotas generadas para el período {{ $periodoActivo->nombre }}.</p>
                    <x-primary-button type="button" wire:click="generarCuotas">Generar cuotas del período {{ $periodoActivo->nombre }}</x-primary-button>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Concepto</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monto</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vencimiento</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($cuotas as $cuota)
                                <tr wire:key="cuota-{{ $cuota->id }}">
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        {{ $cuota->tipo->value === 'matricula' ? 'Matrícula' : "Mensualidad — mes {$cuota->mes}" }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600">${{ number_format($cuota->monto / 100, 2, ',', '.') }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">{{ $cuota->fecha_vencimiento->format('d/m/Y') }}</td>
                                    <td class="px-4 py-2 text-sm">
                                        <x-pill :color="match ($cuota->estado->value) {
                                            'pagada' => 'green',
                                            'parcial' => 'amber',
                                            'pendiente' => 'red',
                                            default => 'gray',
                                        }">
                                            {{ ucfirst($cuota->estado->value) }}
                                        </x-pill>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div>
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Libreta</h2>

            <div class="bg-white shadow-sm rounded-lg p-6">
                @if (! $periodoActivo)
                    <p class="text-sm text-gray-500">No hay ningún período lectivo activo — no se puede generar la libreta.</p>
                @elseif (! $boletin)
                    <p class="text-sm text-gray-600 mb-4">Este alumno todavía no tiene libreta generada para el período {{ $periodoActivo->nombre }}.</p>
                    <x-primary-button type="button" wire:click="generarBoletin">Generar libreta del período {{ $periodoActivo->nombre }}</x-primary-button>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trimestre</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($boletin->trimestres->sortBy('trimestre') as $trimestre)
                                <tr wire:key="trimestre-{{ $trimestre->id }}">
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        {{ $trimestre->trimestre === 4 ? 'Etapa de Apoyo' : "Trimestre {$trimestre->trimestre}" }}
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <x-pill :color="match ($trimestre->estado->value) {
                                            'enviado' => 'green',
                                            'cargado' => 'amber',
                                            default => 'gray',
                                        }">
                                            {{ ucfirst($trimestre->estado->value) }}
                                        </x-pill>
                                    </td>
                                    <td class="px-4 py-2 text-right text-sm">
                                        @if ($trimestre->estado->value !== 'enviado')
                                            <a href="{{ route('boletines.trimestres.cargar', $trimestre) }}" wire:navigate class="text-indigo-600 hover:text-indigo-800">
                                                Cargar
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @endif
</div>
